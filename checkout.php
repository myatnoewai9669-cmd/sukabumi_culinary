<?php
/**
 * checkout.php
 * Checkout: delivery details, payment method, place order, earn reward points.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SHIPPING_FEE')) {
    define('SHIPPING_FEE', 10000);
}
const RUPIAH_PER_POINT = 1000;   // 1 point for every Rp 1.000 spent

// Same session keys as rewards.php
$user_id = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
if ($user_id === 0) {
    header('Location: login.php');
    exit;
}

$payment_methods = [
    'bca'   => 'Bank BCA (Transfer)',
    'gopay' => 'GoPay',
    'ovo'   => 'OVO',
    'dana'  => 'DANA',
    'cod'   => 'Cash on Delivery',
];

// ---------- Success view: ?done=ORDER-CODE ----------
$done_code = trim($_GET['done'] ?? '');
if ($done_code !== '') {
    $os = $pdo->prepare("SELECT * FROM shop_orders WHERE order_code = :c AND user_id = :u");
    $os->execute([':c' => $done_code, ':u' => $user_id]);
    $done = $os->fetch();

    $page_title  = 'Order Placed';
    $active_page = 'cart';
    require_once 'header.php';
    ?>
    <style>
        .done-box { max-width: 560px; margin: 50px auto; text-align: center; background: #fff; border-radius: 24px; padding: 36px; box-shadow: 0 12px 34px rgba(0,0,0,.08); }
        .done-box .big { font-size: 64px; animation: pop .6s ease; }
        @keyframes pop { 0% { transform: scale(0); } 70% { transform: scale(1.2); } 100% { transform: scale(1); } }
        .code { font-family: monospace; background: #fff3c4; padding: 4px 12px; border-radius: 10px; font-weight: 700; }
    </style>
    <section class="section">
        <div class="container">
            <div class="done-box">
                <?php if ($done): ?>
                    <div class="big">🎉</div>
                    <h2>Thank you for your order!</h2>
                    <p>Order <span class="code"><?= e($done['order_code']) ?></span></p>
                    <p>Total: <strong><?= rupiah($done['total']) ?></strong><br>
                       Payment: <?= e($payment_methods[$done['payment_method']] ?? $done['payment_method']) ?></p>
                    <?php if ((int)$done['points_earned'] > 0): ?>
                        <p>⭐ You earned <strong><?= (int)$done['points_earned'] ?> points</strong>.</p>
                    <?php endif; ?>
                    <p>
                        <a href="culinary.php" class="btn btn-primary">Continue Shopping</a>
                        <a href="rewards.php" class="btn">View Rewards</a>
                    </p>
                <?php else: ?>
                    <div class="big">🤔</div>
                    <h2>Order not found</h2>
                    <a href="culinary.php" class="btn btn-primary">Back to Culinary</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    require_once 'footer.php';
    exit;
}

// ---------- Load cart items ----------
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

$ids          = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT id, name, price FROM foods WHERE is_active = 1 AND id IN ($placeholders)"
);
$stmt->execute($ids);

$items    = [];
$subtotal = 0;
foreach ($stmt->fetchAll() as $row) {
    $row['qty']  = (int)$cart[$row['id']];
    $row['line'] = $row['price'] * $row['qty'];
    $subtotal   += $row['line'];
    $items[]     = $row;
}
if (empty($items)) {
    header('Location: cart.php');
    exit;
}

// ---------- Coupon ----------
$discount    = 0;
$coupon_code = $_SESSION['coupon'] ?? '';
if ($coupon_code !== '') {
    try {
        $cs = $pdo->prepare("SELECT * FROM coupons WHERE code = :c AND is_active = 1 LIMIT 1");
        $cs->execute([':c' => $coupon_code]);
        $coupon = $cs->fetch();
        if ($coupon && $subtotal >= (int)$coupon['min_order']) {
            $discount = $coupon['discount_type'] === 'percent'
                ? (int)round($subtotal * $coupon['discount_value'] / 100)
                : (int)$coupon['discount_value'];
            $discount = min($discount, $subtotal);
        } else {
            $coupon_code = '';
        }
    } catch (Exception $ex) {
        $coupon_code = '';
    }
}

$shipping = SHIPPING_FEE;
$total    = max(0, $subtotal - $discount + $shipping);
$points   = (int)floor($total / RUPIAH_PER_POINT);

// ---------- Place the order (POST) ----------
$errors = [];
$old = ['full_name' => '', 'phone' => '', 'address' => '', 'city' => 'Sukabumi', 'notes' => '', 'payment' => 'bca'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['address']   = trim($_POST['address'] ?? '');
    $old['city']      = trim($_POST['city'] ?? '');
    $old['notes']     = trim($_POST['notes'] ?? '');
    $old['payment']   = $_POST['payment'] ?? '';

    if ($old['full_name'] === '') { $errors[] = 'Please enter your full name.'; }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $old['phone'])) { $errors[] = 'Please enter a valid phone number.'; }
    if ($old['address'] === '') { $errors[] = 'Please enter your delivery address.'; }
    if (!isset($payment_methods[$old['payment']])) { $errors[] = 'Please choose a payment method.'; }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $order_code = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $pdo->prepare(
                "INSERT INTO shop_orders
                 (order_code, user_id, full_name, phone, address, city, notes, payment_method,
                  subtotal, discount, shipping, total, coupon_code, points_earned, status)
                 VALUES (:code, :u, :fn, :ph, :ad, :ci, :no, :pm, :sub, :dis, :sh, :tot, :cc, :pts, 'Processing')"
            )->execute([
                ':code' => $order_code, ':u' => $user_id,
                ':fn' => $old['full_name'], ':ph' => $old['phone'], ':ad' => $old['address'],
                ':ci' => $old['city'], ':no' => $old['notes'], ':pm' => $old['payment'],
                ':sub' => $subtotal, ':dis' => $discount, ':sh' => $shipping, ':tot' => $total,
                ':cc' => $coupon_code !== '' ? $coupon_code : null, ':pts' => $points,
            ]);
            $order_id = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare(
                "INSERT INTO shop_order_items (order_id, food_id, food_name, price, qty)
                 VALUES (:o, :f, :n, :p, :q)"
            );
            foreach ($items as $it) {
                $ins->execute([
                    ':o' => $order_id, ':f' => $it['id'], ':n' => $it['name'],
                    ':p' => $it['price'], ':q' => $it['qty'],
                ]);
            }

            if ($points > 0) {
                $pdo->prepare("UPDATE users SET points = points + :p WHERE id = :u")
                    ->execute([':p' => $points, ':u' => $user_id]);
                $pdo->prepare("INSERT INTO point_history (user_id, points, reason) VALUES (:u, :p, :r)")
                    ->execute([':u' => $user_id, ':p' => $points, ':r' => 'Order ' . $order_code]);
            }

            $pdo->commit();

            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);
            header('Location: checkout.php?done=' . urlencode($order_code));
            exit;

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not place your order. Please try again.';
        }
    }
}

$page_title  = 'Checkout';
$active_page = 'cart';
require_once 'header.php';
?>

<style>
    .co-layout { display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; align-items: start; margin-top: 20px; }
    @media (max-width: 850px) { .co-layout { grid-template-columns: 1fr; } }

    .panel { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 8px 26px rgba(0,0,0,.07); margin-bottom: 20px; }
    .panel h3 { margin: 0 0 14px; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; }
    .field input, .field textarea {
        width: 100%; padding: 12px 14px; border: 1px solid #f0e2a8; border-radius: 12px;
        font: inherit; background: #fffdf5; box-sizing: border-box;
    }
    .field input:focus, .field textarea:focus { outline: 2px solid #f5b800; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }

    .pay-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 600px) { .pay-options { grid-template-columns: 1fr; } }
    .pay-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 2px solid #f0e2a8; border-radius: 14px; cursor: pointer; transition: border-color .2s, background .2s; }
    .pay-option:hover { background: #fff8dc; }
    .pay-option:has(input:checked) { border-color: #f5b800; background: #fff3c4; }

    .errors { background: #ffe9e9; color: #b02020; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
    .errors ul { margin: 0; padding-left: 18px; }

    .summary { position: sticky; top: 90px; }
    .summary .row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; font-size: 15px; }
    .summary .row.total { border-top: 2px dashed #f0e2a8; margin-top: 8px; padding-top: 14px; font-size: 20px; font-weight: 700; }
    .summary .discount { color: #2a9d4b; }
    .summary .earn { background: #fffbe9; border-radius: 12px; padding: 10px 12px; margin: 12px 0; font-size: 14px; }
    .btn-block { display: block; width: 100%; text-align: center; border: 0; cursor: pointer; font: inherit; }
    .secure { text-align: center; color: #888; font-size: 13px; margin-top: 12px; }
</style>

<section class="section">
    <div class="container">

        <h1>Checkout</h1>

        <form method="post" class="co-layout" novalidate>

            <!-- ================= LEFT: DETAILS ================= -->
            <div>
                <?php if (!empty($errors)): ?>
                    <div class="errors">
                        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="panel">
                    <h3>📦 Delivery Details</h3>

                    <div class="grid-2">
                        <div class="field">
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="phone">Phone number</label>
                            <input type="text" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="08xxxxxxxxxx" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" required><?= e($old['address']) ?></textarea>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?= e($old['city']) ?>">
                        </div>
                        <div class="field">
                            <label for="notes">Notes (optional)</label>
                            <input type="text" id="notes" name="notes" value="<?= e($old['notes']) ?>">
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h3>💳 Payment Method</h3>
                    <div class="pay-options">
                        <?php foreach ($payment_methods as $key => $label): ?>
                            <label class="pay-option">
                                <input type="radio" name="payment" value="<?= e($key) ?>" <?= $old['payment'] === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <a href="cart.php">&larr; Back to cart</a>
            </div>

            <!-- ================= RIGHT: SUMMARY ================= -->
            <aside class="panel summary">
                <h3>🧾 Order Summary</h3>

                <?php foreach ($items as $it): ?>
                    <div class="row">
                        <span><?= e($it['name']) ?> × <?= (int)$it['qty'] ?></span>
                        <span><?= rupiah($it['line']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="row" style="border-top:1px solid #f4ecc8; margin-top:8px; padding-top:12px;">
                    <span>Subtotal</span><span><?= rupiah($subtotal) ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="row discount"><span>Coupon (<?= e($coupon_code) ?>)</span><span>− <?= rupiah($discount) ?></span></div>
                <?php endif; ?>
                <div class="row"><span>Shipping</span><span><?= rupiah($shipping) ?></span></div>
                <div class="row total"><span>Total</span><span><?= rupiah($total) ?></span></div>

                <div class="earn">⭐ You will earn <strong><?= $points ?> points</strong> with this order.</div>

                <button type="submit" class="btn btn-primary btn-block">Place Order &rarr;</button>
                <div class="secure">🔒 Secure Payment</div>
            </aside>

        </form>
    </div>
</section>

<?php require_once 'footer.php'; ?>