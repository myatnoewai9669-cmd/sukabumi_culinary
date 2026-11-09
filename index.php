<?php
/**
 * index.php
 * Home page: hero, search, quick menu and popular foods.
 */
$page_title  = 'Home';
$active_page = 'home';
require_once 'header.php';

// Popular foods with average rating and review count
$stmt = $pdo->query(
    "SELECT f.*,
        COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r
                  WHERE r.food_id = f.id AND r.status = 'approved'), 0) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r
                  WHERE r.food_id = f.id AND r.status = 'approved') AS review_count
     FROM foods f
     WHERE f.is_active = 1 AND f.is_popular = 1
     ORDER BY f.id ASC
     LIMIT 4"
);
$popular_foods = $stmt->fetchAll();

// Format review count: 2500 -> 2.5k
function short_count($n) {
    return $n >= 1000 ? round($n / 1000, 1) . 'k' : (string)$n;
}

$quick_menu = [
    ['🍴', 'Culinary', 'culinary.php'],
    ['📍', 'Tourism',  'tourism.php'],
    ['🎪', 'Event',    'events.php'],
    ['🏆', 'Rewards',  'rewards.php'],
    ['💬', 'AI Recipe Assistant',     'ai-asssistant.php'],
];
?>

<!-- ================= HERO ================= -->
<section class="hero">
    <div class="container hero-grid">

        <div class="hero-text">
            <h1 class="reveal">
                Discover the Delicious<br>
                <span class="text-yellow">Culinary</span> of<br>
                Sukabumi
            </h1>
            <p class="reveal delay-1">
                Taste the authentic flavors of Sukabumi, West Java
            </p>

            <a href="culinary.php" class="btn btn-primary reveal delay-2">Explore Culinary &rarr;</a>

            <form action="culinary.php" method="get" class="search-bar reveal delay-3">
                <input type="text" name="q" placeholder="Search food, restaurant, or location...">
                <button type="submit" aria-label="Search">🔍</button>
            </form>
        </div>

        <div class="hero-visual reveal delay-2">
            <div class="hero-quote">
                Sukabumi<br><small>More Than Just a Destination</small>
            </div>
            <img src="hero.png.jpeg" alt="Sukabumi culinary" class="float-img">
        </div>

    </div>

    <!-- Wave divider -->
    <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,40 C360,100 1080,-20 1440,40 L1440,80 L0,80 Z" fill="#fffdf5"></path>
    </svg>
</section>

<!-- ================= QUICK MENU ================= -->
<div class="container">
    <div class="quick-menu">
        <?php foreach ($quick_menu as $i => $item): ?>
            <a href="<?= e($item[2]) ?>" class="quick-item reveal" style="transition-delay: <?= $i * 0.08 ?>s">
                <span class="icon"><?= $item[0] ?></span>
                <?= e($item[1]) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ================= POPULAR FOODS ================= -->
<section class="section">
    <div class="container">

        <div class="section-head reveal">
            <h2 class="section-title">Popular Foods in Sukabumi</h2>
            <a href="culinary.php" class="view-all">View All &rarr;</a>
        </div>

        <?php if (empty($popular_foods)): ?>
            <p class="text-muted">No popular foods yet.</p>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($popular_foods as $i => $food): ?>
                    <a href="food-detail.php?slug=<?= e($food['slug']) ?>"
                       class="card food-card reveal"
                       style="transition-delay: <?= $i * 0.1 ?>s">

                        <div class="food-img">
                            <?php if (!empty($food['main_image'])): ?>
                                <img src="uploads/<?= e($food['main_image']) ?>" alt="<?= e($food['name']) ?>">
                            <?php else: ?>
                                <div class="food-placeholder">🍽️</div>
                            <?php endif; ?>
                        </div>

                        
                        <div class="card-body">
                            <h3><?= e($food['name']) ?></h3>
                            <div class="meta">
                                <span class="rating">
                                    ★ <?= number_format((float)$food['avg_rating'], 1) ?>
                                    <small class="text-muted">(<?= short_count((int)$food['review_count']) ?>)</small>
                                </span>

                                <?php if ($food['is_halal']): ?>
                                    <span class="badge badge-halal">Halal</span>
                                <?php elseif (!empty($food['bpom_code'])): ?>
                                    <span class="badge badge-bpom">BPOM</span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:6px;">
                                <span class="price"><?= rupiah($food['price']) ?></span>
                                <?php if (!empty($food['original_price'])): ?>
                                    <span class="price-old"><?= rupiah($food['original_price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
// Scroll reveal animation: adds .visible when an element enters the screen
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