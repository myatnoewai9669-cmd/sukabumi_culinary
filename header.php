<?php
/**
 * header.php
 * Shared top section: <head>, navbar and flash messages.
 *
 * Usage in any page (before any output):
 *   $page_title  = 'Culinary';     // optional
 *   $active_page = 'culinary';     // optional: home, culinary, tourism, about, contact
 *   require_once 'header.php';
 */
require_once 'config.php';

// ---------- Language switch (?lang=id|en|su) ----------
$allowed_langs = ['id' => 'ID', 'en' => 'EN', 'su' => 'SU'];

if (isset($_GET['lang']) && isset($allowed_langs[$_GET['lang']])) {
    $_SESSION['lang'] = $_GET['lang'];

    if (is_logged_in()) {
        $stmt = $pdo->prepare('UPDATE users SET language = ? WHERE id = ?');
        $stmt->execute([$_GET['lang'], $_SESSION['user_id']]);
    }

    // Reload the same page without the lang parameter
    $params = $_GET;
    unset($params['lang']);
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    redirect($path . ($params ? '?' . http_build_query($params) : ''));
}

$current_lang = $_SESSION['lang'] ?? 'id';

// ---------- Page defaults ----------
$page_title  = isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME . ' Culinary Platform';
$active_page = $active_page ?? '';

// ---------- Current user and cart count ----------
$user = current_user();
$cart_count = 0;

if ($user) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $cart_count = (int)$stmt->fetchColumn();
}

// ---------- Navigation items ----------
$nav_items = [
    'home'     => ['Home',     'index.php'],
    'culinary' => ['Culinary', 'culinary.php'],
    'tourism'  => ['Tourism',  'tourism.php'],
    'about'    => ['About',    'about.php'],
    'contact'  => ['Contact',  'contact.php'],
];

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= e($current_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title><?= e($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <div class="container">

        <!-- Logo -->
        <a href="index.php" class="brand">
            <img src="logo.png.jpeg" alt="Kabupaten Sukabumi logo">
            <span>Kabupaten<br>Sukabumi</span>
        </a>

        <!-- Main menu -->
        <nav class="nav-links" id="navLinks">
            <?php foreach ($nav_items as $key => $item): ?>
                <a href="<?= e($item[1]) ?>" class="<?= $active_page === $key ? 'active' : '' ?>">
                    <?= e($item[0]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Right side: language, cart, user -->
        <div class="nav-actions">

            <!-- Language -->
            <div class="dropdown">
                <button type="button" class="icon-btn" data-dropdown>
                    🌐 <?= e($allowed_langs[$current_lang]) ?> ▾
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($allowed_langs as $code => $label): ?>
                        <a href="?lang=<?= e($code) ?>" class="<?= $current_lang === $code ? 'active' : '' ?>">
                            <?= e($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart -->
            <a href="cart.php" class="icon-btn cart-link" aria-label="Cart">
                🛒
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>

            <!-- User -->
            <?php if ($user): ?>
                <div class="dropdown">
                    <button type="button" class="icon-btn" data-dropdown>
                        👤 <?= e(explode(' ', $user['full_name'])[0]) ?> ▾
                    </button>
                    <div class="dropdown-menu">
                        
                        <a href="profile.php">My Profile</a>
                        <a href="orders.php">Order History</a>
                        <a href="wishlist.php">Wishlist</a>
                        <a href="rewards.php">Rewards (<?= (int)$user['points'] ?> pts)</a>

                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="admin-dashboard.php">Admin Panel</a>
                        <?php endif; ?>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>

            <!-- Mobile menu button -->
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu">☰</button>
        </div>

    </div>
</header>

<?php if ($flash): ?>
    <div class="container" style="margin-top:16px;">
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    </div>
<?php endif; ?>

<script>
// Header behaviour: mobile menu and dropdowns
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('navToggle');
    var links  = document.getElementById('navLinks');

    if (toggle && links) {
        toggle.addEventListener('click', function () {
            links.classList.toggle('open');
        });
    }

    var dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(function (dd) {
        var btn = dd.querySelector('[data-dropdown]');
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            dropdowns.forEach(function (other) {
                if (other !== dd) other.classList.remove('open');
            });
            dd.classList.toggle('open');
        });
    });

    document.addEventListener('click', function () {
        dropdowns.forEach(function (dd) { dd.classList.remove('open'); });
    });
});
</script>