<?php
/**
 * admin-sidebar.php
 * Shared admin sidebar. Set $admin_active before including.
 */
$admin_active = $admin_active ?? '';

$admin_menu = [
    'dashboard' => ['📊', 'Dashboard',           'admin-dashboard.php'],
    'foods'     => ['🍴', 'Food Management',     'admin-foods.php'],
    'events'    => ['🎪', 'Event Management',    'admin-events.php'],
    'orders'    => ['🛒', 'Order Management',    'admin-orders.php'],
    'users'     => ['👥', 'User Management',     'admin-users.php'],
    'reviews'   => ['⭐', 'Review Moderation',   'admin-reviews.php'],
    'reports'   => ['📈', 'Reports & Analytics', 'admin-reports.php'],
    'settings'  => ['⚙️', 'Settings',            'admin-settings.php'],
];
?>
<aside class="sidebar">
    <a href="index.php" class="brand">
        <?php if (file_exists(__DIR__ . '/logo.png')): ?>
            <img src="logo.png" alt="Kabupaten Sukabumi logo">
        <?php else: ?>
            <span class="logo-fallback">KS</span>
        <?php endif; ?>
        <span>Kabupaten<br>Sukabumi</span>
    </a>

    <nav class="side-nav">
        <?php foreach ($admin_menu as $key => $item): ?>
            <a href="<?= e($item[2]) ?>" class="<?= $admin_active === $key ? 'active' : '' ?>">
                <span><?= $item[0] ?></span> <?= e($item[1]) ?>
            </a>
        <?php endforeach; ?>
        <a href="logout.php"><span>🚪</span> Logout</a>
    </nav>
</aside>