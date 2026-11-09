<?php
/**
 * culinary.php
 * Culinary listing: search, category filter, halal filter, sorting, pagination.
 */
$page_title  = 'Culinary';
$active_page = 'culinary';
require_once 'header.php';

// ---------- Read filters from the URL ----------
$q        = trim($_GET['q'] ?? '');
$cat      = (int)($_GET['cat'] ?? 0);
$halal    = isset($_GET['halal']) ? 1 : 0;
$sort     = $_GET['sort'] ?? 'popular';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// Only allow known sort options (prevents SQL injection)
$sort_options = [
    'popular'    => 'Most Popular',
    'rating'     => 'Highest Rated',
    'price_low'  => 'Price: Low to High',
    'price_high' => 'Price: High to Low',
    'newest'     => 'Newest',
];
if (!isset($sort_options[$sort])) {
    $sort = 'popular';
}
$order_sql = [
    'popular'    => 'f.is_popular DESC, f.id ASC',
    'rating'     => 'avg_rating DESC, review_count DESC',
    'price_low'  => 'f.price ASC',
    'price_high' => 'f.price DESC',
    'newest'     => 'f.id DESC',
][$sort];

// ---------- Categories (only used if your database has them) ----------
$has_category_col = false;
$categories = [];
try {
    $has_category_col = $pdo->query("SHOW COLUMNS FROM foods LIKE 'category_id'")->rowCount() > 0;
    if ($has_category_col) {
        $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    }
} catch (Exception $ex) {
    $has_category_col = false;
    $categories = [];
}

// ---------- Build the WHERE clause ----------
$where  = ['f.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]       = 'f.name LIKE :q';
    $params[':q']  = '%' . $q . '%';
}
if ($halal) {
    $where[] = 'f.is_halal = 1';
}
if ($has_category_col && $cat > 0) {
    $where[]         = 'f.category_id = :cat';
    $params[':cat']  = $cat;
}
$where_sql = implode(' AND ', $where);

// ---------- Count total results ----------
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM foods f WHERE $where_sql");
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// ---------- Fetch the foods for this page ----------
$sql = "SELECT f.*,
            COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r
                      WHERE r.food_id = f.id AND r.status = 'approved'), 0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r
                      WHERE r.food_id = f.id AND r.status = 'approved') AS review_count
        FROM foods f
        WHERE $where_sql
        ORDER BY $order_sql
        LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$foods = $stmt->fetchAll();

// ---------- Helpers ----------
function short_count($n) {
    return $n >= 1000 ? round($n / 1000, 1) . 'k' : (string)$n;
}

// Build a URL that keeps the current filters but changes some values
function culinary_url(array $change = []) {
    $query = array_merge($_GET, $change);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            unset($query[$key]);
        }
    }
    $string = http_build_query($query);
    return 'culinary.php' . ($string ? '?' . $string : '');
}
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0 0 18px; color: #6b6b6b; }

    .filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 22px 0 14px; }
    .filter-bar select, .filter-bar .check {
        padding: 10px 14px; border: 1px solid #f0e2a8; border-radius: 12px;
        background: #fff; font: inherit; cursor: pointer;
    }
    .filter-bar .check { display: inline-flex; gap: 8px; align-items: center; }
    .filter-bar .spacer { flex: 1; }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
    .chip {
        padding: 8px 16px; border-radius: 999px; background: #fff; color: #444;
        border: 1px solid #f0e2a8; text-decoration: none; font-size: 14px;
        transition: transform .2s, background .2s;
    }
    .chip:hover { transform: translateY(-2px); background: #fff3c4; }
    .chip.active { background: var(--yellow, #f5b800); border-color: var(--yellow, #f5b800); color: #222; font-weight: 600; }

    .result-info { color: #777; margin: 10px 0 18px; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state .big { font-size: 56px; animation: wobble 2s ease-in-out infinite; }
    @keyframes wobble { 0%,100% { transform: rotate(-8deg); } 50% { transform: rotate(8deg); } }

    .pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin: 36px 0 10px; }
    .pagination a, .pagination span {
        min-width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 12px; background: #fff; border: 1px solid #f0e2a8; color: #444;
        text-decoration: none; padding: 0 12px;
    }
    .pagination a:hover { background: #fff3c4; }
    .pagination .current { background: var(--yellow, #f5b800); border-color: var(--yellow, #f5b800); font-weight: 700; }
    .pagination .disabled { opacity: .4; }
</style>

<!-- ================= PAGE HEADER + SEARCH ================= -->
<section class="page-hero">
    <div class="container">
        <h1 class="reveal">Sukabumi <span class="text-yellow">Culinary</span></h1>
        <p class="reveal delay-1">Find traditional foods, snacks and drinks from Sukabumi.</p>

        <form action="culinary.php" method="get" class="search-bar reveal delay-2">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search food, restaurant, or location...">
            <?php if ($cat): ?><input type="hidden" name="cat" value="<?= (int)$cat ?>"><?php endif; ?>
            <?php if ($halal): ?><input type="hidden" name="halal" value="1"><?php endif; ?>
            <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
            <button type="submit" aria-label="Search">🔍</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= CATEGORY CHIPS ================= -->
        <?php if (!empty($categories)): ?>
            <div class="chips reveal">
                <a href="<?= e(culinary_url(['cat' => 0, 'page' => 1])) ?>"
                   class="chip <?= $cat === 0 ? 'active' : '' ?>">All</a>
                <?php foreach ($categories as $c): ?>
                    <a href="<?= e(culinary_url(['cat' => $c['id'], 'page' => 1])) ?>"
                       class="chip <?= $cat === (int)$c['id'] ? 'active' : '' ?>">
                        <?= e($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ================= FILTER BAR ================= -->
        <form method="get" class="filter-bar reveal">
            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
            <?php if ($cat): ?><input type="hidden" name="cat" value="<?= (int)$cat ?>"><?php endif; ?>

            <label class="check">
                <input type="checkbox" name="halal" value="1" <?= $halal ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                Halal only
            </label>

            <span class="spacer"></span>

            <select name="sort" onchange="this.form.submit()" aria-label="Sort foods">
                <?php foreach ($sort_options as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <p class="result-info">
            <?= $total ?> food<?= $total === 1 ? '' : 's' ?> found
            <?php if ($q !== ''): ?> for "<strong><?= e($q) ?></strong>"<?php endif; ?>
            <?php if ($q !== '' || $cat || $halal): ?>
                &middot; <a href="culinary.php">Clear filters</a>
            <?php endif; ?>
        </p>

        <!-- ================= FOOD GRID ================= -->
        <?php if (empty($foods)): ?>
            <div class="empty-state">
                <div class="big">🍽️</div>
                <h3>No food found</h3>
                <p class="text-muted">Try another keyword or clear the filters.</p>
                <a href="culinary.php" class="btn btn-primary">Show all foods</a>
            </div>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($foods as $i => $food): ?>
                    <a href="food-detail.php?slug=<?= e($food['slug']) ?>"
                       class="card food-card reveal"
                       style="transition-delay: <?= ($i % 4) * 0.1 ?>s">

                        <div class="food-img">
                            <?php if (!empty($food['main_image'])): ?>
                                <img src="uploads/<?= e($food['main_image']) ?>" alt="<?= e($food['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="food-placeholder" style="display:none;">🍽️</div>
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

            <!-- ================= PAGINATION ================= -->
            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Pages">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(culinary_url(['page' => $page - 1])) ?>">&larr; Prev</a>
                    <?php else: ?>
                        <span class="disabled">&larr; Prev</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e(culinary_url(['page' => $p])) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= e(culinary_url(['page' => $page + 1])) ?>">Next &rarr;</a>
                    <?php else: ?>
                        <span class="disabled">Next &rarr;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
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