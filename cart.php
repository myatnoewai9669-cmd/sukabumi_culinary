<?php
/**
 * cart.php
 * Shopping cart (session based): add, change quantity, remove, clear, coupon, totals.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SHIPPING_FEE')) {
    define('SHIPPING_FEE', 10000);
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];   // food_id => quantity
}

// ---------- Handle actions (before any HTML is printed) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $food_id = (int)($_POST['food_id'] ?? 0);

    switch ($action) {

        case 'add':
            $qty = max(1, min(99, (int)($_POST['qty'] ?? 1)));
            $chk = $pdo->prepare("SELECT id FROM foods WHERE id = :id AND is_active = 1");
            $chk->execute([':id' => $food_id]);
            if ($chk->fetch()) {
                $current = $_SESSION['cart'][$food_id] ?? 0;
                $_SESSION['cart'][$food_id] = min(99, $current + $qty);
                $_SESSION['flash'] = 'Added to your cart.';
            }
            break;

        case 'change':
            if (isset($_SESSION['cart'][$food_id])) {
                $new = $_SESSION['cart'][$food_id] + (int)($_POST['change'] ?? 0);
                if ($new < 1) {
                    unset($_SESSION['cart'][$food_id]);
                } else {
                    $_SESSION['cart'][$food_id] = min(99, $new);
                }
            }
            break;

        case 'remove':
            unset($_SESSION['cart'][$food_id]);
            $_SESSION['flash'] = 'Item removed.';
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);
            $_SESSION['flash'] = 'Cart cleared.';
            break;

        case 'apply_coupon':
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $found = null;
            try {
                $cs = $pdo->prepare("SELECT * FROM coupons WHERE code = :c AND is_active = 1 LIMIT 1");
                $cs->execute([':c' => $code]);
                $found = $cs->fetch();
            } catch (Exception $ex) {
                $found = null;
            }
            if ($found) {
                $_SESSION['coupon'] = $found['code'];
                $_SESSION['flash']  = 'Coupon applied.';
            } else {
                unset($_SESSION['coupon']);
                $_SESSION['flash'] = 'Invalid coupon code.';
            }
            break;

        case 'remove_coupon':
            unset($_SESSION['coupon']);
            break;
    }

    header('Location: cart.php');   // prevents double submit on refresh
    exit;
}

// ---------- Load cart items from the database ----------
$items    = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    $ids          = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name, slug, price, main_image
         FROM foods WHERE is_active = 1 AND id IN ($placeholders)"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $row) {
        $qty = (int)$_SESSION['cart'][$row['id']];
        $row['qty']      = $qty;
        $row['line']     = $row['price'] * $qty;
        $subtotal       += $row['line'];
        $items[]         = $row;
    }

    // Remove foods that no longer exist
    $found_ids = array_column($items, 'id');
    foreach ($ids as $id) {
        if (!in_array($id, $found_ids)) {
            unset($_SESSION['cart'][$id]);
        }
    }
}

// ---------- Coupon discount ----------
$discount     = 0;
$coupon_code  = $_SESSION['coupon'] ?? '';
$coupon_error = '';

if ($coupon_code !== '' && $subtotal > 0) {
    try {
        $cs = $pdo->prepare("SELECT * FROM coupons WHERE code = :c AND is_active = 1 LIMIT 1");
        $cs->execute([':c' => $coupon_code]);
        $coupon = $cs->fetch();

        if ($coupon && $subtotal >= (int)$coupon['min_order']) {
            $discount = $coupon['discount_type'] === 'percent'
                ? (int)round($subtotal * $coupon['discount_value'] / 100)
                : (int)$coupon['discount_value'];
            $discount = min($discount, $subtotal);
        } elseif ($coupon) {
            $coupon_error = 'Minimum order for this coupon is ' . rupiah($coupon['min_order']) . '.';
        }
    } catch (Exception $ex) {
        $coupon_code = '';
    }
}

$shipping = $subtotal > 0 ? SHIPPING_FEE : 0;
$total    = max(0, $subtotal - $discount + $shipping);

// ---------- Page output ----------
$page_title  = 'Your Cart';
$active_page = 'cart';
require_once 'header.php';

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>

<style>
    .cart-head { display: flex; justify-content: space-between; align-items: center; margin: 26px 0 18px; flex-wrap: wrap; gap: 10px; }
    .cart-head h1 { margin: 0; }
    .btn-clear { background: #ffe9e9; color: #d33; border: 0; padding: 10px 16px; border-radius: 12px; cursor: pointer; font: inherit; transition: transform .2s; }
    .btn-clear:hover { transform: scale(1.05); }

    .flash { background: #fff8dc; border: 1px solid #f0e2a8; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; animation: fadeIn .4s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

    .cart-layout { display: grid; grid-template-columns: 1.6fr 1fr; gap: 28px; align-items: start; }
    @media (max-width: 850px) { .cart-layout { grid-template-columns: 1fr; } }

    .cart-item {
        display: grid; grid-template-columns: 80px 1fr auto auto auto; gap: 14px; align-items: center;
        background: #fff; border-radius: 16px; padding: 12px; margin-bottom: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,.05); transition: transform .25s;
    }
    .cart-item:hover { transform: translateY(-2px); }
    @media (max-width: 600px) { .cart-item { grid-template-columns: 70px 1fr; } .cart-item > *:nth-child(n+3) { grid-column: 2; } }

    .cart-thumb { width: 80px; height: 80px; border-radius: 12px; overflow: hidden; background: #fff3c4; }
    .cart-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .cart-thumb .ph { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 32px; }
    .cart-item h3 { margin: 0 0 4px; font-size: 16px; }
    .cart-item h3 a { color: inherit; text-decoration: none; }

    .qty-form { display: inline-flex; align-items: center; border: 1px solid #f0e2a8; border-radius: 10px; overflow: hidden; background: #fff; }
    .qty-form button { width: 34px; height: 36px; border: 0; background: #fff8dc; font-size: 18px; cursor: pointer; }
    .qty-form button:hover { background: #ffe9a0; }
    .qty-form span { min-width: 34px; text-align: center; font-weight: 600; }

    .line-total { font-weight: 700; }
    .btn-trash { background: none; border: 0; cursor: pointer; font-size: 18px; transition: transform .2s; }
    .btn-trash:hover { transform: scale(1.25) rotate(8deg); }

    .summary { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 8px 26px rgba(0,0,0,.07); position: sticky; top: 90px; }
    .summary .row { display: flex; justify-content: space-between; padding: 8px 0; }
    .summary .row.total { border-top: 2px dashed #f0e2a8; margin-top: 8px; padding-top: 14px; font-size: 20px; font-weight: 700; }
    .summary .discount { color: #2a9d4b; }
    .coupon-box { margin: 18px 0; padding: 14px; background: #fffbe9; border-radius: 14px; }
    .coupon-box form { display: flex; gap: 8px; margin-top: 8px; }
    .coupon-box input { flex: 1; padding: 10px 12px; border: 1px solid #f0e2a8; border-radius: 10px; font: inherit; text-transform: uppercase; }
    .coupon-box small.err { color: #d33; display: block; margin-top: 6px; }
    .btn-block { display: block; width: 100%; text-align: center; }

    .empty-cart { text-align: center; padding: 70px 20px; }
    .empty-cart .big { font-size: 64px; animation: bounce 1.6s ease-in-out infinite; }
    @keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
</style>

<section class="section">
    <div class="container">

        <div class="cart-head">
            <h1>🛒 Your Cart</h1>
            <?php if (!empty($items)): ?>
                <form method="post" onsubmit="return confirm('Clear the whole cart?');">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn-clear">🗑 Clear Cart</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flash !== ''): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="empty-cart">
                <div class="big">🛒</div>
                <h2>Your cart is empty</h2>
                <p class="text-muted">Find something tasty from Sukabumi.</p>
                <a href="culinary.php" class="btn btn-primary">Explore Culinary &rarr;</a>
            </div>

        <?php else: ?>
            <div class="cart-layout">

                <!-- ================= ITEMS ================= -->
                <div>
                    <?php foreach ($items as $item): ?>
                        <div class="cart-item">
                            <div class="cart-thumb">
                                <?php if (!empty($item['main_image'])): ?>
                                    <img src="uploads/<?= e($item['main_image']) ?>" alt="<?= e($item['name']) ?>"
                                         onerror="this.outerHTML='<div class=&quot;ph&quot;>🍽️</div>';">
                                <?php else: ?>
                                    <div class="ph">🍽️</div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h3><a href="food-detail.php?slug=<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3>
                                <span class="text-muted"><?= rupiah($item['price']) ?></span>
                            </div>

                            <form method="post" class="qty-form">
                                <input type="hidden" name="action" value="change">
                                <input type="hidden" name="food_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" name="change" value="-1" aria-label="Decrease">−</button>
                                <span><?= (int)$item['qty'] ?></span>
                                <button type="submit" name="change" value="1" aria-label="Increase">+</button>
                            </form>

                            <div class="line-total"><?= rupiah($item['line']) ?></div>

                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="food_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn-trash" aria-label="Remove">🗑️</button>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <a href="culinary.php">&larr; Continue shopping</a>
                </div>

                <!-- ================= SUMMARY ================= -->
                <aside class="summary">
                    <div class="row"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>

                    <?php if ($discount > 0): ?>
                        <div class="row discount"><span>Coupon (<?= e($coupon_code) ?>)</span><span>− <?= rupiah($discount) ?></span></div>
                    <?php endif; ?>

                    <div class="row"><span>Shipping</span><span><?= rupiah($shipping) ?></span></div>
                    <div class="row total"><span>Total</span><span><?= rupiah($total) ?></span></div>

                    <div class="coupon-box">
                        <strong>🎁 Have a coupon code?</strong>
                        <?php if ($coupon_code !== '' && $discount > 0): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="remove_coupon">
                                <input type="text" value="<?= e($coupon_code) ?>" disabled>
                                <button type="submit" class="btn">Remove</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="action" value="apply_coupon">
                                <input type="text" name="code" placeholder="Enter code" value="<?= e($coupon_code) ?>">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </form>
                            <?php if ($coupon_error !== ''): ?>
                                <small class="err"><?= e($coupon_error) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <a href="checkout.php" class="btn btn-primary btn-block">Proceed to Checkout &rarr;</a>
                </aside>

            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once 'footer.php'; ?>