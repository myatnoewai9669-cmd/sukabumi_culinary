<?php
/**
 * about.php
 * About page: mission, live numbers from the database, key features, call to action.
 */
require_once 'config.php';

$page_title  = 'About';
$active_page = 'about';
require_once 'header.php';

// ---------- Live numbers (each one is safe if its table is missing) ----------
function count_rows($pdo, $sql) {
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Exception $ex) {
        return 0;
    }
}

$stats = [
    ['🍴', count_rows($pdo, "SELECT COUNT(*) FROM foods WHERE is_active = 1"),           'Local Foods'],
    ['📍', count_rows($pdo, "SELECT COUNT(*) FROM tourism_places WHERE is_active = 1"),  'Tourism Places'],
    ['🎪', count_rows($pdo, "SELECT COUNT(*) FROM events WHERE is_active = 1"),          'Events'],
    ['🗺️', count_rows($pdo, "SELECT COUNT(*) FROM locations WHERE is_active = 1"),       'Map Locations'],
];

$features = [
    ['🌐', 'Multilingual',    'Browse in Indonesian, English or Sundanese.'],
    ['✅', 'Halal & BPOM',    'See which foods are halal certified or BPOM registered.'],
    ['🗺️', 'Food Map',        'Find restaurants, food centers and local sellers near you.'],
    ['🏆', 'Foodie Rewards',  'Earn points with every order and redeem vouchers.'],
    ['🎪', 'Events',          'Join food festivals, workshops and fairs.'],
    ['🔒', 'Easy Checkout',   'Order in a few steps with simple payment options.'],
];
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 56px 0 44px; text-align: center; }
    .page-hero h1 { margin: 0 0 10px; font-size: 38px; }
    .page-hero p { margin: 0 auto; max-width: 620px; color: #6b6b6b; }

    .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 36px; align-items: center; }
    @media (max-width: 800px) { .about-grid { grid-template-columns: 1fr; } }
    .about-img { border-radius: 22px; overflow: hidden; aspect-ratio: 4 / 3; background: #fff3c4; box-shadow: 0 12px 34px rgba(0,0,0,.1); }
    .about-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
    .about-img:hover img { transform: scale(1.06); }
    .about-img .ph { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 90px; }
    .about-text h2 { margin: 0 0 12px; }
    .about-text p { color: #555; line-height: 1.7; }

    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin: 50px 0; }
    .stat { background: #fff; border-radius: 18px; padding: 22px; text-align: center; box-shadow: 0 6px 20px rgba(0,0,0,.06); transition: transform .25s; }
    .stat:hover { transform: translateY(-4px); }
    .stat .icon { font-size: 32px; }
    .stat .num { font-size: 34px; font-weight: 800; margin: 4px 0; }
    .stat .lbl { color: #777; font-size: 14px; }

    .section-title { text-align: center; margin: 0 0 24px; }
    .feat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
    .feat { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 6px 20px rgba(0,0,0,.06); transition: transform .25s; }
    .feat:hover { transform: translateY(-4px); }
    .feat .icon { font-size: 30px; }
    .feat h3 { margin: 8px 0 6px; font-size: 17px; }
    .feat p { margin: 0; color: #777; font-size: 14px; }

    .cta { margin-top: 60px; text-align: center; background: linear-gradient(135deg, #fff3c4, #ffe08a); border-radius: 24px; padding: 40px 20px; }
    .cta h2 { margin: 0 0 8px; }
    .cta p { margin: 0 0 18px; color: #7a5b00; }
    .cta .btn + .btn { margin-left: 8px; }
</style>

<section class="page-hero">
    <div class="container">
        <h1 class="reveal">About <span class="text-yellow">Sukabumi Culinary</span></h1>
        <p class="reveal delay-1">Taste &bull; Explore &bull; Enjoy. A platform to discover the authentic flavors and places of Sukabumi, West Java.</p>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= MISSION ================= -->
        <div class="about-grid">
            <div class="about-img reveal">
                <img src="hero.png.jpeg" alt="Sukabumi culinary"
                     onerror="this.outerHTML='<div class=&quot;ph&quot;>🍽️</div>';">
            </div>
            <div class="about-text reveal delay-1">
                <h2>More Than Just a Destination</h2>
                <p>
                    Sukabumi is known for its traditional foods, beautiful nature and warm culture.
                    This platform brings them together in one place, so visitors and locals can
                    find where to eat, what to try and where to go.
                </p>
                <p>
                    We want to help local food makers and small businesses (UMKM) reach more people,
                    and make it easy for everyone to enjoy the taste of Sukabumi.
                </p>
                <a href="culinary.php" class="btn btn-primary">Explore Culinary &rarr;</a>
            </div>
        </div>

        <!-- ================= LIVE NUMBERS ================= -->
        <div class="stats">
            <?php foreach ($stats as $i => $s): ?>
                <div class="stat reveal" style="transition-delay: <?= $i * 0.08 ?>s">
                    <div class="icon"><?= $s[0] ?></div>
                    <div class="num" data-count="<?= (int)$s[1] ?>">0</div>
                    <div class="lbl"><?= e($s[2]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ================= FEATURES ================= -->
        <h2 class="section-title reveal">What You Can Do Here</h2>
        <div class="feat-grid">
            <?php foreach ($features as $i => $f): ?>
                <div class="feat reveal" style="transition-delay: <?= ($i % 3) * 0.08 ?>s">
                    <div class="icon"><?= $f[0] ?></div>
                    <h3><?= e($f[1]) ?></h3>
                    <p><?= e($f[2]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ================= CALL TO ACTION ================= -->
        <div class="cta reveal">
            <h2>Ready to taste Sukabumi?</h2>
            <p>Find your next favorite food, place or event.</p>
            <a href="culinary.php" class="btn btn-primary">Explore Culinary</a>
            <a href="help.php#contact" class="btn">Contact Us</a>
        </div>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Count-up animation for the numbers
    function countUp(el) {
        var target = parseInt(el.dataset.count, 10) || 0;
        var start = null, duration = 1200;
        function step(ts) {
            if (start === null) { start = ts; }
            var p = Math.min(1, (ts - start) / duration);
            el.textContent = Math.round(target * p);
            if (p < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    }

    // Scroll reveal animation
    var items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('visible'); });
        document.querySelectorAll('[data-count]').forEach(function (n) { n.textContent = n.dataset.count; });
        return;
    }
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                var n = entry.target.querySelector('[data-count]');
                if (n) { countUp(n); }
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    items.forEach(function (el) { observer.observe(el); });
});
</script>

<?php require_once 'footer.php'; ?>