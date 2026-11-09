<?php
/**
 * orders.php
 * Order history: list of the user's orders with status and items.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Same session keys as checkout.php and rewards.php
$user_id = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
if ($user_id === 0) {
    header('Location: login.php');
    exit;
}

$page_title  = 'Order History';
$active_page = 'orders';
require_once 'header.php';

$payment_methods = [
    'bca'   => 'Bank BCA (Transfer)',
    'gopay' => 'GoPay',
    'ovo'   => 'OVO',
    'dana'  => 'DANA',
    'cod'   => 'Cash on Delivery',
];

// Status flow shown as a progress line
$steps = ['Processing', 'Shipped', 'Delivered'];

// ---------- Filter by status ----------
$filter = $_GET['status'] ?? 'All';
$allowed = array_merge(['All'], $steps, ['Cancelled']);
if (!in_array($filter, $allowed, true)) {
    $filter = 'All';
}

// ---------- Load orders ----------
$sql    = "SELECT * FROM shop_orders WHERE user_id = :u";
$params = [':u' => $user_id];
if ($filter !== 'All') {
    $sql .= " AND status = :s";
    $params[':s'] = $filter;
}
$sql .= " ORDER BY id DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ---------- Load items for all these orders in one query ----------
$items_by_order = [];
if (!empty($orders)) {
    $order_ids    = array_map(function ($o) { return (int)$o['id']; }, $orders);
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $is = $pdo->prepare(
        "SELECT order_id, food_name, price, qty FROM shop_order_items
         WHERE order_id IN ($placeholders) ORDER BY id ASC"
    );
    $is->execute($order_ids);
    foreach ($is->fetchAll() as $row) {
        $items_by_order[$row['order_id']][] = $row;
    }
}

function status_class($status) {
    switch ($status) {
        case 'Delivered': return 'st-delivered';
        case 'Shipped':   return 'st-shipped';
        case 'Cancelled': return 'st-cancelled';
        default:          return 'st-processing';
    }
}
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0; color: #6b6b6b; }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 22px 0 18px; }
    .chip {
        padding: 8px 16px; border-radius: 999px; background: #fff; color: #444;
        border: 1px solid #f0e2a8; text-decoration: none; font-size: 14px; transition: transform .2s, background .2s;
    }
    .chip:hover { transform: translateY(-2px); background: #fff3c4; }
    .chip.active { background: var(--yellow, #f5b800); border-color: var(--yellow, #f5b800); color: #222; font-weight: 600; }

    .order-card { background: #fff; border-radius: 18px; padding: 20px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(0,0,0,.06); transition: transform .25s; }
    .order-card:hover { transform: translateY(-3px); }
    .order-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .order-code { font-weight: 700; font-size: 17px; }
    .order-date { color: #888; font-size: 13px; }

    .badge-status { padding: 5px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; }
    .st-processing { background: #fff3c4; color: #8a6400; }
    .st-shipped    { background: #dcecff; color: #1a5fb4; }
    .st-delivered  { background: #d9f5e1; color: #1f7a3a; }
    .st-cancelled  { background: #ffe1e1; color: #b02020; }

    .progress { display: flex; align-items: center; margin: 18px 0 8px; }
    .progress .step { display: flex; flex-direction: column; align-items: center; flex: none; font-size: 12px; color: #aaa; width: 78px; }
    .progress .dot { width: 22px; height: 22px; border-radius: 50%; background: #eee; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #fff; }
    .progress .line { flex: 1; height: 3px; background: #eee; margin-bottom: 20px; }
    .progress .done .dot { background: #f5b800; }
    .progress .done { color: #444; font-weight: 600; }
    .progress .line.done { background: #f5b800; }

    .order-meta { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 10px; color: #555; font-size: 14px; }
    .order-total { font-weight: 700; font-size: 18px; color: #222; }

    details { margin-top: 12px; }
    summary { cursor: pointer; color: #b07a00; font-weight: 600; }
    .item-row { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f4ecc8; font-size: 14px; }
    .item-row:last-child { border-bottom: 0; }
    .detail-box { background: #fffbe9; border-radius: 12px; padding: 12px 14px; margin-top: 10px; font-size: 14px; color: #555; }
    .detail-box .row { display: flex; justify-content: space-between; padding: 3px 0; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state .big { font-size: 60px; animation: bounce 1.6s ease-in-out infinite; }
    @keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
</style>

<section class="page-hero">
    <div class="container">
        <h1 class="reveal">My <span class="text-yellow">Orders</span></h1>
        <p class="reveal delay-1">Track your purchases and see what you ordered.</p>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= STATUS FILTER ================= -->
        <div class="chips reveal">
            <?php foreach ($allowed as $st): ?>
                <a href="orders.php<?= $st === 'All' ? '' : '?status=' . urlencode($st) ?>"
                   class="chip <?= $filter === $st ? 'active' : '' ?>"><?= e($st) ?></a>
            <?php endforeach; ?>
        </div>

        <!-- ================= ORDERS ================= -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="big">📦</div>
                <h2>No orders yet</h2>
                <p class="text-muted">
                    <?= $filter === 'All' ? 'Your orders will appear here after checkout.' : 'No orders with this status.' ?>
                </p>
                <a href="culinary.php" class="btn btn-primary">Explore Culinary &rarr;</a>
            </div>

        <?php else: ?>
            <?php foreach ($orders as $i => $o):
                $status    = $o['status'];
                $cancelled = ($status === 'Cancelled');
                $step_now  = array_search($status, $steps, true);
                if ($step_now === false) { $step_now = -1; }
                $items = $items_by_order[$o['id']] ?? [];
            ?>
                <div class="order-card reveal" style="transition-delay: <?= ($i % 4) * 0.06 ?>s">

                    <div class="order-top">
                        <div>
                            <div class="order-code"><?= e($o['order_code']) ?></div>
                            <div class="order-date"><?= e(date('j M Y, H:i', strtotime($o['created_at']))) ?></div>
                        </div>
                        <span class="badge-status <?= status_class($status) ?>"><?= e($status) ?></span>
                    </div>

                    <?php if (!$cancelled): ?>
                        <div class="progress" aria-label="Order progress">
                            <?php foreach ($steps as $idx => $label): ?>
                                <div class="step <?= $idx <= $step_now ? 'done' : '' ?>">
                                    <div class="dot"><?= $idx <= $step_now ? '✓' : '' ?></div>
                                    <?= e($label) ?>
                                </div>
                                <?php if ($idx < count($steps) - 1): ?>
                                    <div class="line <?= $idx < $step_now ? 'done' : '' ?>"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="order-meta">
                        <span><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
                            &middot; <?= e($payment_methods[$o['payment_method']] ?? $o['payment_method']) ?></span>
                        <span class="order-total"><?= rupiah($o['total']) ?></span>
                    </div>

                    <details>
                        <summary>View details</summary>

                        <?php foreach ($items as $it): ?>
                            <div class="item-row">
                                <span><?= e($it['food_name']) ?> × <?= (int)$it['qty'] ?></span>
                                <span><?= rupiah($it['price'] * $it['qty']) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="detail-box">
                            <div class="row"><span>Subtotal</span><span><?= rupiah($o['subtotal']) ?></span></div>
                            <?php if ((int)$o['discount'] > 0): ?>
                                <div class="row"><span>Coupon (<?= e($o['coupon_code']) ?>)</span><span>− <?= rupiah($o['discount']) ?></span></div>
                            <?php endif; ?>
                            <div class="row"><span>Shipping</span><span><?= rupiah($o['shipping']) ?></span></div>
                            <div class="row"><strong>Total</strong><strong><?= rupiah($o['total']) ?></strong></div>
                            <?php if ((int)$o['points_earned'] > 0): ?>
                                <div class="row"><span>⭐ Points earned</span><span>+<?= (int)$o['points_earned'] ?></span></div>
                            <?php endif; ?>
                        </div>

                        <div class="detail-box">
                            <strong>Delivered to</strong><br>
                            <?= e($o['full_name']) ?> &middot; <?= e($o['phone']) ?><br>
                            <?= e($o['address']) ?><?= $o['city'] !== '' && $o['city'] !== null ? ', ' . e($o['city']) : '' ?>
                            <?php if (!empty($o['notes'])): ?><br><em>Note: <?= e($o['notes']) ?></em><?php endif; ?>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</section>

<script>
// Scroll reveal animation
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.reveal');

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    items.forEach(function (el) { observer.observe(el); });
});
</script>

<?php require_once 'footer.php'; ?>