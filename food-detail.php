c<?php
/**
 * food-detail.php
 * Single food page: image, price, badges, description, reviews, add to cart.
 */
$active_page = 'culinary';
require_once 'header.php';

// ---------- Load the food by slug ----------
$slug = trim($_GET['slug'] ?? '');

$stmt = $pdo->prepare(
    "SELECT f.*,
        COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r
                  WHERE r.food_id = f.id AND r.status = 'approved'), 0) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r
                  WHERE r.food_id = f.id AND r.status = 'approved') AS review_count
     FROM foods f
     WHERE f.slug = :slug AND f.is_active = 1
     LIMIT 1"
);
$stmt->execute([':slug' => $slug]);
$food = $stmt->fetch();

if (!$food) {
    http_response_code(404);
    $page_title = 'Food not found';
    ?>
    <section class="section">
        <div class="container" style="text-align:center; padding:60px 0;">
            <div style="font-size:56px;">🍽️</div>
            <h2>Food not found</h2>
            <p class="text-muted">This food does not exist or is no longer available.</p>
            <a href="culinary.php" class="btn btn-primary">Back to Culinary</a>
        </div>
    </section>
    <?php
    require_once 'footer.php';
    exit;
}

$page_title = $food['name'];

// ---------- Approved reviews ----------
$reviews = [];
try {
    $rs = $pdo->prepare(
        "SELECT * FROM reviews
         WHERE food_id = :id AND status = 'approved'
         ORDER BY id DESC LIMIT 10"
    );
    $rs->execute([':id' => $food['id']]);
    $reviews = $rs->fetchAll();
} catch (Exception $ex) {
    $reviews = [];
}

// ---------- Related foods ----------
$rel = $pdo->prepare(
    "SELECT id, name, slug, price, main_image
     FROM foods
     WHERE is_active = 1 AND id <> :id
     ORDER BY is_popular DESC, id ASC
     LIMIT 4"
);
$rel->execute([':id' => $food['id']]);
$related = $rel->fetchAll();

// ---------- Helpers ----------
function short_count($n) {
    return $n >= 1000 ? round($n / 1000, 1) . 'k' : (string)$n;
}

$discount = 0;
if (!empty($food['original_price']) && $food['original_price'] > $food['price']) {
    $discount = (int)round((1 - $food['price'] / $food['original_price']) * 100);
}
?>

<style>
    .breadcrumb { display: flex; gap: 8px; flex-wrap: wrap; font-size: 14px; color: #888; margin: 20px 0; }
    .breadcrumb a { color: #888; text-decoration: none; }
    .breadcrumb a:hover { color: #222; }

    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 36px; align-items: start; }
    @media (max-width: 800px) { .detail-grid { grid-template-columns: 1fr; } }

    .detail-img { border-radius: 20px; overflow: hidden; background: #fff3c4; aspect-ratio: 4 / 3; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
    .detail-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
    .detail-img:hover img { transform: scale(1.06); }
    .detail-placeholder { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 90px; }

    .detail-info h1 { margin: 0 0 8px; }
    .badges { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; }
    .price-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 12px 0; }
    .price-big { font-size: 30px; font-weight: 700; color: var(--yellow, #f5b800); }
    .discount-tag { background: #ffe1e1; color: #d33; padding: 3px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; }

    .info-list { list-style: none; padding: 0; margin: 16px 0; color: #555; }
    .info-list li { padding: 5px 0; }

    .buy-row { display: flex; gap: 14px; align-items: center; margin-top: 18px; }
    .qty { display: inline-flex; align-items: center; border: 1px solid #f0e2a8; border-radius: 12px; overflow: hidden; background: #fff; }
    .qty button { width: 40px; height: 44px; border: 0; background: #fff8dc; font-size: 20px; cursor: pointer; transition: background .2s; }
    .qty button:hover { background: #ffe9a0; }
    .qty input { width: 48px; text-align: center; border: 0; font: inherit; font-weight: 600; }
    .buy-row .btn { flex: 1; }

    .tabs { margin-top: 44px; }
    .tab-buttons { display: flex; gap: 22px; border-bottom: 2px solid #f4ecc8; }
    .tab-buttons button {
        background: none; border: 0; padding: 12px 4px; font: inherit; cursor: pointer; color: #888;
        border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color .2s;
    }
    .tab-buttons button.active { color: #222; font-weight: 600; border-bottom-color: var(--yellow, #f5b800); }
    .tab-panel { display: none; padding: 22px 0; animation: fadeIn .4s; }
    .tab-panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .review { padding: 14px 0; border-bottom: 1px solid #f4ecc8; }
    .review .who { font-weight: 600; }
    .review .stars { color: #f5b800; }

    .related-title { margin: 50px 0 16px; }
</style>

<section class="section">
    <div class="container">

        <!-- ================= BREADCRUMB ================= -->
        <div class="breadcrumb">
            <a href="index.php">🏠</a> &rsaquo;
            <a href="culinary.php">Culinary</a> &rsaquo;
            <span><?= e($food['name']) ?></span>
        </div>

        <!-- ================= MAIN DETAIL ================= -->
        <div class="detail-grid">

            <div class="detail-img reveal">
                <?php if (!empty($food['main_image'])): ?>
                    <img src="uploads/<?= e($food['main_image']) ?>" alt="<?= e($food['name']) ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="detail-placeholder" style="display:none;">🍽️</div>
                <?php else: ?>
                    <div class="detail-placeholder">🍽️</div>
                <?php endif; ?>
            </div>

            <div class="detail-info reveal delay-1">
                <h1><?= e($food['name']) ?></h1>

                <div class="rating">
                    ★ <?= number_format((float)$food['avg_rating'], 1) ?>
                    <small class="text-muted">(<?= short_count((int)$food['review_count']) ?> reviews)</small>
                </div>

                <div class="badges">
                    <?php if ($food['is_halal']): ?>
                        <span class="badge badge-halal">Halal Certified</span>
                    <?php endif; ?>
                    <?php if (!empty($food['bpom_code'])): ?>
                        <span class="badge badge-bpom">BPOM</span>
                    <?php endif; ?>
                </div>

                <div class="price-row">
                    <span class="price-big"><?= rupiah($food['price']) ?></span>
                    <?php if (!empty($food['original_price'])): ?>
                        <span class="price-old"><?= rupiah($food['original_price']) ?></span>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                        <span class="discount-tag">-<?= $discount ?>%</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($food['description'])): ?>
                    <p><?= e($food['description']) ?></p>
                <?php endif; ?>

                <ul class="info-list">
                    <?php if (!empty($food['origin'])): ?>
                        <li>📍 <strong>Origin:</strong> <?= e($food['origin']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($food['ingredients'])): ?>
                        <li>🥜 <strong>Ingredients:</strong> <?= e($food['ingredients']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($food['availability'])): ?>
                        <li>📅 <strong>Available:</strong> <?= e($food['availability']) ?></li>
                    <?php endif; ?>
                </ul>

                <!-- Add to cart -->
                <form action="cart.php" method="post" class="buy-row">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="food_id" value="<?= (int)$food['id'] ?>">

                    <div class="qty">
                        <button type="button" id="qty-minus" aria-label="Decrease">−</button>
                        <input type="number" name="qty" id="qty" value="1" min="1" max="99">
                        <button type="button" id="qty-plus" aria-label="Increase">+</button>
                    </div>

                    <button type="submit" class="btn btn-primary">🛒 Add to Cart</button>
                </form>
                <?php
// Is this food already in the wishlist?
$in_wishlist = false;
$wl_user = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
if ($wl_user > 0) {
    try {
        $w = $pdo->prepare("SELECT id FROM wishlist_items WHERE user_id = :u AND food_id = :f");
        $w->execute([':u' => $wl_user, ':f' => $food['id']]);
        $in_wishlist = (bool)$w->fetch();
    } catch (Exception $ex) {
        $in_wishlist = false;
    }
}
?>
<form action="wishlist.php" method="post" style="margin-top:12px;">
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="food_id" value="<?= (int)$food['id'] ?>">
    <input type="hidden" name="redirect" value="food-detail.php?slug=<?= e($food['slug']) ?>">
    <button type="submit" class="btn">
        <?= $in_wishlist ? '❤️ Saved to Wishlist' : '🤍 Add to Wishlist' ?>
    </button>
</form>
            </div>
        </div>

        <!-- ================= TABS ================= -->
        <div class="tabs reveal">
            <div class="tab-buttons">
                <button type="button" class="active" data-tab="tab-desc">Description</button>
                <button type="button" data-tab="tab-reviews">Reviews (<?= short_count((int)$food['review_count']) ?>)</button>
                <button type="button" data-tab="tab-location">Location</button>
            </div>

            <div class="tab-panel active" id="tab-desc">
                <p><?= !empty($food['description']) ? nl2br(e($food['description'])) : '<span class="text-muted">No description yet.</span>' ?></p>
            </div>

            <div class="tab-panel" id="tab-reviews">
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet. Be the first to review this food!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review">
                            <div class="who"><?= e($r['user_name'] ?? 'Anonymous') ?></div>
                            <div class="stars"><?= str_repeat('★', (int)($r['rating'] ?? 0)) ?><?= str_repeat('☆', 5 - (int)($r['rating'] ?? 0)) ?></div>
                            <p><?= e($r['comment'] ?? '') ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-panel" id="tab-location">
                <p>📍 <?= !empty($food['origin']) ? e($food['origin']) : 'Sukabumi, West Java' ?></p>
                <a href="tourism.php" class="btn btn-primary">Explore Sukabumi</a>
            </div>
        </div>

        <!-- ================= RELATED FOODS ================= -->
        <?php if (!empty($related)): ?>
            <h2 class="related-title reveal">You may also like</h2>
            <div class="grid grid-4">
                <?php foreach ($related as $i => $rf): ?>
                    <a href="food-detail.php?slug=<?= e($rf['slug']) ?>"
                       class="card food-card reveal"
                       style="transition-delay: <?= $i * 0.1 ?>s">
                        <div class="food-img">
                            <?php if (!empty($rf['main_image'])): ?>
                                <img src="uploads/<?= e($rf['main_image']) ?>" alt="<?= e($rf['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="food-placeholder" style="display:none;">🍽️</div>
                            <?php else: ?>
                                <div class="food-placeholder">🍽️</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3><?= e($rf['name']) ?></h3>
                            <span class="price"><?= rupiah($rf['price']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Quantity buttons
    var qty = document.getElementById('qty');
    document.getElementById('qty-minus').addEventListener('click', function () {
        qty.value = Math.max(1, parseInt(qty.value || 1, 10) - 1);
    });
    document.getElementById('qty-plus').addEventListener('click', function () {
        qty.value = Math.min(99, parseInt(qty.value || 1, 10) + 1);
    });

    // Tabs
    var buttons = document.querySelectorAll('.tab-buttons button');
    var panels  = document.querySelectorAll('.tab-panel');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
            panels.forEach(function (p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
        });
    });

    // Scroll reveal animation
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