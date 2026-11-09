<?php
/**
 * footer.php
 * Shared bottom section: footer, mobile bottom navigation, closing tags.
 *
 * Usage: require_once 'footer.php'; at the very end of every public page.
 * Requires header.php to be included first.
 */

// Fallbacks in case a page includes this file on its own
$active_page = $active_page ?? '';
$cart_count  = $cart_count ?? 0;
$user        = $user ?? null;
?>

<!-- ================= FOOTER ================= -->
<footer class="footer">
    <div class="container">

        <div class="footer-grid">

            <div class="footer-col">
                <a href="index.php" class="brand">
                    <img src="logo.png.jpeg" alt="Kabupaten Sukabumi logo">
                    <span>Kabupaten<br>Sukabumi</span>
                </a>
                <p>Kabupaten Sukabumi. All rights reserved. &middot; Created by Myat Noe Wai</p>
            </div>

            <div class="footer-col">
                <h4>Explore</h4>
                <ul>
                    <li><a href="culinary.php">Culinary</a></li>
                    <li><a href="tourism.php">Tourism</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="map.php">Culinary Map</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>My Account</h4>
                <ul>
                    <?php if ($user): ?>
                        <li><a href="profile.php">My Profile</a></li>
                        <li><a href="orders.php">Order History</a></li>
                        <li><a href="rewards.php">Foodie Rewards</a></li>
                        <li><a href="logout.php">Logout</a></li>
            
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="forgot-password.php">Forgot Password</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Help</h4>
                <ul>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="help.php">Help Center</a></li>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>

        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
            <span class="footer-tagline">Taste &bull; Explore &bull; Enjoy</span>
        </div>

    </div>
</footer>

<!-- ================= MOBILE BOTTOM NAVIGATION ================= -->
<nav class="bottom-nav" aria-label="Mobile navigation">
    <a href="index.php" class="<?= $active_page === 'home' ? 'active' : '' ?>">
        <span class="bn-icon">🏠</span>
        <span>Home</span>
    </a>
    <a href="culinary.php" class="<?= $active_page === 'culinary' ? 'active' : '' ?>">
        <span class="bn-icon">🍴</span>
        <span>Culinary</span>
    </a>
    <a href="cart.php" class="<?= $active_page === 'cart' ? 'active' : '' ?>">
        <span class="bn-icon">
            🛒
            <?php if ($cart_count > 0): ?>
                <span class="cart-count"><?= (int)$cart_count ?></span>
            <?php endif; ?>
        </span>
        <span>Cart</span>
    </a>
    <a href="<?= $user ? 'profile.php' : 'login.php' ?>" class="<?= $active_page === 'profile' ? 'active' : '' ?>">
        <span class="bn-icon">👤</span>
        <span>Profile</span>
    </a>
</nav>

<script>
// Lazy loading for images, plus auto-hide for flash messages
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('img:not([loading])').forEach(function (img) {
        img.setAttribute('loading', 'lazy');
    });

    var alertBox = document.querySelector('.alert');
    if (alertBox) {
        setTimeout(function () {
            alertBox.style.transition = 'opacity 0.5s';
            alertBox.style.opacity = '0';
            setTimeout(function () { alertBox.remove(); }, 500);
        }, 5000);
    }
});
</script>

</body>
</html>