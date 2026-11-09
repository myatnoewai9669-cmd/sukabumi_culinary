<?php
/**
 * tourism.php
 * Tourism listing: search and category filter.
 */
$page_title  = 'Tourism';
$active_page = 'tourism';
require_once 'header.php';

// ---------- Read filters ----------
$q   = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');

// ---------- Category list ----------
$categories = $pdo->query(
    "SELECT DISTINCT category FROM tourism_places
     WHERE is_active = 1 ORDER BY category ASC"
)->fetchAll(PDO::FETCH_COLUMN);

// ---------- Build query ----------
$where  = ['is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]      = '(name LIKE :q OR location LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($cat !== '') {
    $where[]        = 'category = :cat';
    $params[':cat'] = $cat;
}

$stmt = $pdo->prepare(
    "SELECT * FROM tourism_places
     WHERE " . implode(' AND ', $where) . "
     ORDER BY is_popular DESC, rating DESC, id ASC"
);
$stmt->execute($params);
$places = $stmt->fetchAll();

// Keep current filters when building links
function tourism_url(array $change = []) {
    $query = array_merge($_GET, $change);
    foreach ($query as $k => $v) {
        if ($v === '' || $v === null) {
            unset($query[$k]);
        }
    }
    $s = http_build_query($query);
    return 'tourism.php' . ($s ? '?' . $s : '');
}

// Emoji per category (used when the image is missing)
$category_icons = [
    'Beach'    => '🏖️',
    'Waterfall'=> '💦',
    'Mountain' => '⛰️',
    'Nature'   => '🌿',
];
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0 0 18px; color: #6b6b6b; }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 22px 0 8px; }
    .chip {
        padding: 8px 16px; border-radius: 999px; background: #fff; color: #444;
        border: 1px solid #f0e2a8; text-decoration: none; font-size: 14px;
        transition: transform .2s, background .2s;
    }
    .chip:hover { transform: translateY(-2px); background: #fff3c4; }
    .chip.active { background: var(--yellow, #f5b800); border-color: var(--yellow, #f5b800); color: #222; font-weight: 600; }

    .result-info { color: #777; margin: 10px 0 18px; }

    .place-img { height: 180px; overflow: hidden; border-radius: 14px 14px 0 0; background: #fff3c4; }
    .place-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
    .place-card:hover .place-img img { transform: scale(1.08); }
    .place-placeholder {
        height: 100%; display: flex; align-items: center; justify-content: center; font-size: 56px;
    }
    .place-loc { color: #777; font-size: 13px; margin: 4px 0 6px; }
    .place-desc {
        color: #555; font-size: 13px; margin: 6px 0 0;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .badge-cat { background: #fff3c4; color: #8a6400; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state .big { font-size: 56px; animation: wobble 2s ease-in-out infinite; }
    @keyframes wobble { 0%,100% { transform: rotate(-8deg); } 50% { transform: rotate(8deg); } }
</style>

<!-- ================= PAGE HEADER ================= -->
<section class="page-hero">
    <div class="container">
        <h1 class="reveal">Sukabumi <span class="text-yellow">Tourism</span></h1>
        <p class="reveal delay-1">Beaches, waterfalls, mountains and nature to explore.</p>

        <form action="tourism.php" method="get" class="search-bar reveal delay-2">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search place or location...">
            <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
            <button type="submit" aria-label="Search">🔍</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= CATEGORY CHIPS ================= -->
        <?php if (!empty($categories)): ?>
            <div class="chips reveal">
                <a href="<?= e(tourism_url(['cat' => ''])) ?>"
                   class="chip <?= $cat === '' ? 'active' : '' ?>">All</a>
                <?php foreach ($categories as $c): ?>
                    <a href="<?= e(tourism_url(['cat' => $c])) ?>"
                       class="chip <?= $cat === $c ? 'active' : '' ?>">
                        <?= e($category_icons[$c] ?? '📍') ?> <?= e($c) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="result-info">
            <?= count($places) ?> place<?= count($places) === 1 ? '' : 's' ?> found
            <?php if ($q !== ''): ?> for "<strong><?= e($q) ?></strong>"<?php endif; ?>
            <?php if ($q !== '' || $cat !== ''): ?>
                &middot; <a href="tourism.php">Clear filters</a>
            <?php endif; ?>
        </p>

        <!-- ================= PLACES GRID ================= -->
        <?php if (empty($places)): ?>
            <div class="empty-state">
                <div class="big">🗺️</div>
                <h3>No place found</h3>
                <p class="text-muted">Try another keyword or clear the filters.</p>
                <a href="tourism.php" class="btn btn-primary">Show all places</a>
            </div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($places as $i => $place): ?>
                    <div class="card place-card reveal" style="transition-delay: <?= ($i % 3) * 0.1 ?>s">

                        <div class="place-img">
                            <?php if (!empty($place['image'])): ?>
                                <img src="uploads/<?= e($place['image']) ?>" alt="<?= e($place['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="place-placeholder" style="display:none;">
                                    <?= e($category_icons[$place['category']] ?? '📍') ?>
                                </div>
                            <?php else: ?>
                                <div class="place-placeholder">
                                    <?= e($category_icons[$place['category']] ?? '📍') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <h3><?= e($place['name']) ?></h3>
                            <div class="place-loc">📍 <?= e($place['location']) ?></div>

                            <div class="meta">
                                <span class="rating">★ <?= number_format((float)$place['rating'], 1) ?></span>
                                <span class="badge badge-cat"><?= e($place['category']) ?></span>
                            </div>

                            <p class="place-desc"><?= e($place['description']) ?></p>

                            <div style="margin-top:8px;">
                                <span class="price">
                                    <?= (int)$place['ticket_price'] === 0 ? 'Free' : rupiah($place['ticket_price']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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