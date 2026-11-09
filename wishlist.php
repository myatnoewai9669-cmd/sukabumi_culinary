<?php
// wishlist.php - the member's saved foods
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------- Settings: change these if your project uses other names ----------
$FOOD_TABLE   = 'foods';                 // table that holds the foods
$IMAGE_FOLDER = 'uploads/main_image/';   // main folder that holds the food photos
$DETAIL_URL   = 'food-detail.php';       // food detail page (receives ?id=)
$CART_URL     = 'checkout.php';          
$DEBUG        = isset($_GET['debug']);   // open wishlist.php?debug=1 to see which image paths are used

// ---------- Helpers ----------
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function wl_pick(array $row, array $keys, $default = null) {
  foreach ($keys as $k) {
    if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
  }
  return $default;
}
function wl_json($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}
// Find the photo of a food: known column names first, then any column that looks like an image file
function wl_find_image(array $f, array $folders) {
  $val = '';
  foreach (['image', 'main_image', 'photo', 'thumbnail', 'img', 'picture', 'image_url', 'food_image', 'cover'] as $k) {
    if (!empty($f[$k]) && is_string($f[$k])) { $val = $f[$k]; break; }
  }
  if ($val === '') {
    foreach ($f as $v) {
      if (is_string($v) && preg_match('/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i', $v)) { $val = $v; break; }
    }
  }
  if ($val === '') return '';
  if (preg_match('#^(https?:)?//#i', $val)) return $val;     // full web address

  $val  = ltrim(str_replace('\\', '/', $val), '/');
  $base = basename($val);
  $try  = [$val];
  foreach ($folders as $dir) {
    $dir   = rtrim($dir, '/') . '/';
    $try[] = $dir . $val;
    $try[] = $dir . $base;
  }
  foreach ($try as $path) {
    if (is_file(__DIR__ . '/' . $path)) return $path;       // the first file that really exists
  }
  return rtrim($folders[0], '/') . '/' . $base;               // best guess
}
function wl_url($p) {
  if ($p === '' || preg_match('#^(https?:)?//#i', $p)) return $p;
  return implode('/', array_map('rawurlencode', explode('/', $p)));
}

if (!isset($pdo)) die('config.php must create the $pdo database connection.');
if (!preg_match('/^[A-Za-z0-9_]+$/', $FOOD_TABLE)) die('Invalid table name.');

// ---------- Look at the real tables, so the code works with your column names ----------
try {
  $wlCols = $pdo->query('SHOW COLUMNS FROM wishlists')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $ex) {
  die('The "wishlists" table was not found. Create it first.');
}
if (!in_array('user_id', $wlCols, true) || !in_array('food_id', $wlCols, true)) {
  die('The "wishlists" table needs the columns user_id and food_id. It has: ' . e(implode(', ', $wlCols)));
}

$timeCol = null;   // the time the food was saved (optional column)
foreach (['created_at', 'added_at', 'date_added', 'created'] as $c) {
  if (in_array($c, $wlCols, true)) { $timeCol = $c; break; }
}

$pk = 'id';        // primary key of the foods table
try {
  $r = $pdo->query("SHOW KEYS FROM `$FOOD_TABLE` WHERE Key_name = 'PRIMARY'")->fetch(PDO::FETCH_ASSOC);
  if ($r && preg_match('/^[A-Za-z0-9_]+$/', $r['Column_name'])) $pk = $r['Column_name'];
} catch (Throwable $ex) {
  die('The foods table "' . e($FOOD_TABLE) . '" was not found. Change $FOOD_TABLE at the top of wishlist.php.');
}

$uid = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

// One security token per session (protects add/remove requests)
if (empty($_SESSION['wl_token'])) $_SESSION['wl_token'] = bin2hex(random_bytes(32));

// Other pages can ask for the token before sending an add/toggle request
if (($_GET['action'] ?? '') === 'token') {
  if (!$uid) wl_json(['ok' => false, 'message' => 'Please log in first.'], 401);
  wl_json(['ok' => true, 'token' => $_SESSION['wl_token']]);
}

// ---------- Add / remove / toggle ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ajax = ($_POST['ajax'] ?? '') === '1';
  $fail = function ($msg, $code) use ($ajax) {
    if ($ajax) wl_json(['ok' => false, 'message' => $msg], $code);
    header('Location: wishlist.php');
    exit;
  };

  if (!$uid) $fail('Please log in first.', 401);
  if (!hash_equals($_SESSION['wl_token'], (string)($_POST['csrf'] ?? ''))) $fail('Invalid request.', 403);

  $action = $_POST['action'] ?? '';
  $foodId = (int)($_POST['food_id'] ?? 0);
  if ($foodId < 1 || !in_array($action, ['add', 'remove', 'toggle'], true)) $fail('Invalid request.', 400);

  try {
    $st = $pdo->prepare('SELECT 1 FROM wishlists WHERE user_id=? AND food_id=?');
    $st->execute([$uid, $foodId]);
    $exists = (bool)$st->fetchColumn();

    if ($action === 'add' || ($action === 'toggle' && !$exists)) {
      $st = $pdo->prepare("SELECT 1 FROM `$FOOD_TABLE` WHERE `$pk`=?");
      $st->execute([$foodId]);
      if (!$st->fetchColumn()) $fail('Food not found.', 404);

      if (!$exists) {
        $pdo->prepare('INSERT INTO wishlists (user_id, food_id) VALUES (?, ?)')->execute([$uid, $foodId]);
      }
      $saved = true;
    } else {
      $pdo->prepare('DELETE FROM wishlists WHERE user_id=? AND food_id=?')->execute([$uid, $foodId]);
      $saved = false;
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id=?');
    $st->execute([$uid]);
    $count = (int)$st->fetchColumn();
  } catch (Throwable $ex) {
    $fail('Something went wrong. Please try again.', 500);
  }

  if ($ajax) wl_json(['ok' => true, 'saved' => $saved, 'count' => $count]);
  header('Location: wishlist.php');
  exit;
}

// ---------- Page: guests go to the login page ----------
if (!$uid) { header('Location: login.php'); exit; }

$addedSql = $timeCol ? "w.`$timeCol`" : 'NULL';
$orderSql = $timeCol ? "w.`$timeCol` DESC" : "f.`$pk` DESC";

$st = $pdo->prepare("SELECT f.*, $addedSql AS wl_added
                     FROM wishlists w
                     JOIN `$FOOD_TABLE` f ON f.`$pk` = w.food_id
                     WHERE w.user_id = ?
                     ORDER BY $orderSql");
$st->execute([$uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Folders that are searched for the photos
$folders = [$IMAGE_FOLDER, 'uploads/', 'uploads/foods/', 'uploads/food/', 'images/', 'assets/images/', 'img/'];

$items = [];
foreach ($rows as $f) {
  $src = wl_find_image($f, $folders);

  $halalRaw = wl_pick($f, ['halal', 'is_halal', 'halal_status', 'halal_certified']);
  $isHalal  = $halalRaw !== null
              && !in_array(strtolower((string)$halalRaw), ['0', 'no', 'false', 'non-halal', 'non halal'], true);

  $price    = (float)wl_pick($f, ['price'], 0);
  $oldPrice = (float)wl_pick($f, ['old_price', 'original_price'], 0);
  $old      = $oldPrice > $price ? $oldPrice : 0;

  $items[] = [
    'id'     => (int)$f[$pk],
    'name'   => (string)wl_pick($f, ['name', 'food_name', 'title'], 'Food #' . $f[$pk]),
    'price'  => $price,
    'old'    => $old,
    'off'    => $old ? (int)round((1 - $price / $old) * 100) : 0,
    'rating' => wl_pick($f, ['rating', 'avg_rating', 'rating_avg', 'average_rating', 'stars']),
    'src'    => $src,
    'exists' => $src !== '' && is_file(__DIR__ . '/' . $src),
    'halal'  => $isHalal,
  ];
}

// Sorting (done here, so it works with any column names)
$sort = $_GET['sort'] ?? 'new';
if (!in_array($sort, ['new', 'price_asc', 'price_desc', 'name'], true)) $sort = 'new';
if ($sort === 'price_asc')  usort($items, fn($a, $b) => $a['price'] <=> $b['price']);
if ($sort === 'price_desc') usort($items, fn($a, $b) => $b['price'] <=> $a['price']);
if ($sort === 'name')       usort($items, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$count = count($items);
$total = array_sum(array_column($items, 'price'));
$placeholder = 'data:image/svg+xml;utf8,' . rawurlencode(
  '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="#fff3c4"/>'
  . '<text x="50%" y="52%" font-size="72" text-anchor="middle" dominant-baseline="middle">🍽</text></svg>');

$pageTitle = 'My Wishlist';
require 'header.php';
?>

<style>
/* ---------- Wishlist (yellow theme) ---------- */
.wl-page{--y:#ffc928;--yd:#c98f00;--soft:#fff8dc;--text:#2b2410;--muted:#8a7f5c;--line:#f3e7b8;
  max-width:1180px;margin:0 auto;padding:clamp(100px,12vw,132px) 16px 90px;color:var(--text);box-sizing:border-box}
.wl-page *{box-sizing:border-box}
.wl-page [hidden]{display:none!important}
.wl-page button,.wl-page select{font-family:inherit}

/* Banner */
.wl-banner{position:relative;overflow:hidden;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
  gap:16px;margin-bottom:28px;padding:22px 26px;border-radius:26px;
  background:linear-gradient(135deg,#ffe066,#ffc928 55%,#ffb703);box-shadow:0 14px 30px rgba(245,184,0,.35)}
.wl-banner::after{content:"";position:absolute;right:-60px;top:-70px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.25)}
.wl-title{position:relative;z-index:1;display:flex;align-items:center;gap:14px}
.wl-title h1{margin:0;font-size:28px;line-height:1.1;color:var(--text)}
.wl-badge-ic{width:52px;height:52px;border-radius:50%;background:#fff;color:#e63946;display:grid;place-items:center;
  font-size:27px;box-shadow:0 6px 16px rgba(0,0,0,.15);animation:wlBeat 1.8s ease-in-out infinite}
@keyframes wlBeat{0%,70%,100%{transform:scale(1)}14%{transform:scale(1.18)}28%{transform:scale(1)}42%{transform:scale(1.12)}}
.wl-count{margin:4px 0 0;font-size:14px;color:#6b5a14}
.wl-tools{position:relative;z-index:1;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.wl-total{background:#fff;border-radius:30px;padding:9px 16px;font-size:13px;color:var(--muted)}
.wl-total strong{color:var(--yd);font-size:16px}
.wl-sort{margin:0}
.wl-sort select{border:0;background:#fff;border-radius:30px;padding:10px 16px;font-size:13px;cursor:pointer;outline:none;
  box-shadow:0 4px 10px rgba(0,0,0,.08)}

/* Cards */
.wl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:22px}
.wl-card{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:22px;
  overflow:hidden;box-shadow:0 6px 18px rgba(201,143,0,.14);transition:transform .25s,box-shadow .25s;
  animation:wlIn .55s cubic-bezier(.2,.8,.2,1) backwards;animation-delay:calc(var(--i,0) * 80ms)}
.wl-card:hover{transform:translateY(-8px);box-shadow:0 20px 36px rgba(201,143,0,.3)}
@keyframes wlIn{from{opacity:0;transform:translateY(28px) scale(.94)}to{opacity:1;transform:none}}
.wl-card.leaving{animation:wlOut .4s ease forwards;pointer-events:none}
@keyframes wlOut{to{opacity:0;transform:scale(.8) rotate(-4deg)}}

.wl-img{display:block;aspect-ratio:4/3;overflow:hidden;background:var(--soft)}
.wl-img img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .6s}
.wl-card:hover .wl-img img{transform:scale(1.1)}

.wl-heart{position:absolute;top:12px;right:12px;z-index:2;width:40px;height:40px;padding:0;border:0;border-radius:50%;
  background:#fff;color:#e63946;font-size:21px;line-height:1;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.2);
  transition:transform .2s,background .2s}
.wl-heart:hover{transform:scale(1.18);background:#fff0f0}
.wl-heart.pop{animation:wlPop .4s ease}
@keyframes wlPop{40%{transform:scale(1.5)}100%{transform:scale(.8)}}

.wl-body{display:flex;flex-direction:column;flex:1;padding:14px 16px 16px}
.wl-body h3{margin:0 0 6px;font-size:16px;font-weight:700}
.wl-body h3 a{color:inherit;text-decoration:none}
.wl-body h3 a:hover{color:var(--yd)}
.wl-meta{display:flex;align-items:center;gap:8px;min-height:22px;margin-bottom:8px;font-size:12.5px}
.wl-star{color:var(--yd);font-weight:700}
.wl-halal{background:#e4f6e8;color:#1a7a35;font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px}
.wl-price{display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:14px;font-size:19px;font-weight:800;color:var(--yd)}
.wl-price s{font-size:12px;font-weight:400;color:var(--muted)}
.wl-off{background:#fee2e2;color:#dc2626;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px}
.wl-body form{margin:auto 0 0}

.wl-btn{display:block;width:100%;border:0;border-radius:14px;padding:12px 14px;cursor:pointer;background:var(--y);
  color:var(--text);font-weight:800;font-size:14px;text-align:center;text-decoration:none;
  transition:transform .2s,box-shadow .2s,background .2s}
.wl-btn:hover{background:#ffd54d;transform:translateY(-2px);box-shadow:0 10px 20px rgba(201,143,0,.35)}
.wl-btn:active{transform:scale(.97)}
.wl-btn-inline{display:inline-block;width:auto;padding:13px 28px}

.wl-empty{text-align:center;padding:64px 18px;background:#fff;border:2px dashed var(--line);border-radius:26px;
  animation:wlIn .5s ease backwards}
.wl-empty-ic{font-size:74px;animation:wlFloat 3s ease-in-out infinite}
@keyframes wlFloat{50%{transform:translateY(-12px) rotate(6deg)}}
.wl-empty h2{margin:10px 0 6px;font-size:22px}
.wl-empty p{margin:0 0 20px;color:var(--muted)}

.wl-debug{margin-top:26px;padding:14px;background:#2b2410;color:#ffe066;border-radius:12px;font-size:12px;
  white-space:pre-wrap;word-break:break-all}

.wl-toast{position:fixed;left:50%;bottom:28px;transform:translate(-50%,20px);background:#2b2410;color:#fff;
  padding:11px 20px;border-radius:26px;font-size:13.5px;opacity:0;pointer-events:none;
  transition:opacity .3s,transform .3s;z-index:9999}
.wl-toast.show{opacity:1;transform:translate(-50%,0)}

@media (max-width:560px){
  .wl-banner{padding:18px}
  .wl-title h1{font-size:22px}
  .wl-grid{grid-template-columns:repeat(2,1fr);gap:12px}
  .wl-body{padding:10px 12px 12px}
  .wl-body h3{font-size:14px}
  .wl-price{font-size:15px}
  .wl-heart{width:34px;height:34px;font-size:18px}
}
@media (prefers-reduced-motion:reduce){.wl-page *{animation:none!important;transition:none!important}}
</style>

<main class="wl-page">
  <div class="wl-banner">
    <div class="wl-title">
      <span class="wl-badge-ic">♥</span>
      <div>
        <h1>My Wishlist</h1>
        <p class="wl-count"><span class="wl-count-num"><?= $count ?></span> saved food<?= $count === 1 ? '' : 's' ?></p>
      </div>
    </div>

    <div class="wl-tools" id="wlTools" <?= $count ? '' : 'hidden' ?>>
      <span class="wl-total">Total <strong id="wlTotal">Rp <?= number_format($total, 0, ',', '.') ?></strong></span>
      <form method="get" class="wl-sort">
        <select name="sort" onchange="this.form.submit()" aria-label="Sort wishlist">
          <option value="new"        <?= $sort === 'new' ? 'selected' : '' ?>>Newest first</option>
          <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
        </select>
      </form>
    </div>
  </div>

  <div class="wl-grid" id="wlGrid" data-token="<?= e($_SESSION['wl_token']) ?>" <?= $count ? '' : 'hidden' ?>>
    <?php foreach ($items as $i => $it): ?>
      <article class="wl-card" data-id="<?= $it['id'] ?>" data-price="<?= $it['price'] ?>" style="--i:<?= $i ?>">
        <a class="wl-img" href="<?= e($DETAIL_URL) ?>?id=<?= $it['id'] ?>">
          <img src="<?= e($it['src'] !== '' ? wl_url($it['src']) : $placeholder) ?>" alt="<?= e($it['name']) ?>" loading="lazy"
               onerror="this.onerror=null;this.src='<?= $placeholder ?>'">
        </a>
        <button type="button" class="wl-heart" aria-label="Remove from wishlist">♥</button>

        <div class="wl-body">
          <h3><a href="<?= e($DETAIL_URL) ?>?id=<?= $it['id'] ?>"><?= e($it['name']) ?></a></h3>
          <div class="wl-meta">
            <?php if ($it['rating'] !== null): ?><span class="wl-star">★ <?= number_format((float)$it['rating'], 1) ?></span><?php endif; ?>
            <?php if ($it['halal']): ?><span class="wl-halal">Halal</span><?php endif; ?>
          </div>
          <div class="wl-price">
            Rp <?= number_format($it['price'], 0, ',', '.') ?>
            <?php if ($it['old']): ?>
              <s>Rp <?= number_format($it['old'], 0, ',', '.') ?></s>
              <?php if ($it['off'] > 0): ?><span class="wl-off">-<?= $it['off'] ?>%</span><?php endif; ?>
            <?php endif; ?>
          </div>

          <form method="post" action="<?= e($CART_URL) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="food_id" value="<?= $it['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="wl-btn">🛒 Add to Cart</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="wl-empty" id="wlEmpty" <?= $count ? 'hidden' : '' ?>>
    <div class="wl-empty-ic">🍜</div>
    <h2>Your wishlist is empty</h2>
    <p>Tap the heart on any dish to save it here for later.</p>
    <a href="culinary.php" class="wl-btn wl-btn-inline">Explore Culinary →</a>
  </div>

  <?php if ($DEBUG): ?>
    <div class="wl-debug"><?php
      echo 'Columns in the foods table: ' . e($rows ? implode(', ', array_keys($rows[0])) : '(no rows)') . "\n\n";
      foreach ($items as $it) {
        echo e($it['name']) . ' -> image: ' . e($it['src'] !== '' ? $it['src'] : '(no image column found)')
           . ' | file exists: ' . ($it['exists'] ? 'YES' : 'NO') . "\n";
      }
    ?></div>
  <?php endif; ?>
</main>

<div class="wl-toast" id="wlToast" role="status" aria-live="polite"></div>

<script>
(function () {
  var grid  = document.getElementById('wlGrid');
  if (!grid) return;
  var token = grid.dataset.token;
  var empty = document.getElementById('wlEmpty');
  var tools = document.getElementById('wlTools');
  var totalEl = document.getElementById('wlTotal');
  var toast = document.getElementById('wlToast');
  var timer;

  function say(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(timer);
    timer = setTimeout(function () { toast.classList.remove('show'); }, 2200);
  }

  function recalc(count) {
    var sum = 0;
    grid.querySelectorAll('.wl-card').forEach(function (c) { sum += parseFloat(c.dataset.price) || 0; });
    totalEl.textContent = 'Rp ' + sum.toLocaleString('id-ID');
    document.querySelectorAll('.wl-count-num').forEach(function (el) { el.textContent = count; });
    if (count === 0) {
      grid.hidden = true;
      tools.hidden = true;
      empty.hidden = false;
    }
  }

  // Heart button: remove the food from the wishlist
  grid.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.wl-heart');
    if (!btn || btn.disabled) return;
    var card = btn.closest('.wl-card');
    btn.disabled = true;
    btn.classList.add('pop');

    var body = new FormData();
    body.append('action', 'remove');
    body.append('food_id', card.dataset.id);
    body.append('csrf', token);
    body.append('ajax', '1');

    fetch('wishlist.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.message || 'Something went wrong');
        card.classList.add('leaving');
        say('Removed from your wishlist');
        setTimeout(function () { card.remove(); recalc(d.count); }, 400);
      })
      .catch(function (err) {
        btn.disabled = false;
        btn.classList.remove('pop');
        say(err.message || 'Something went wrong');
      });
  });
})();
</script>

<?php require 'footer.php'; ?>