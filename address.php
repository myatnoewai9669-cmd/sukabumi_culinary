<?php
session_start();

$defaults = [
    'name'   => 'Myat Noe Wai',
    'email'  => 'myatnoewai@gmail.com',
    'phone'  => '+62 812 3456 7890',
    'address'=> 'Jl. Melati No. 12, Sukabumi, Jawa Barat, Indonesia',
    'avatar' => '',
];
$user = array_merge($defaults, is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : []);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Addresses (demo data in session; replace with database) ----
if (!isset($_SESSION['addresses']) || !is_array($_SESSION['addresses'])) {
    $_SESSION['addresses'] = [
        ['id' => 1, 'label' => 'Home', 'recipient' => $user['name'], 'phone' => $user['phone'],
         'address' => 'Jl. Melati No. 12', 'city' => 'Sukabumi', 'postal' => '43111', 'default' => true],
    ];
}
$addresses = &$_SESSION['addresses'];

$labels = ['Home', 'Office', 'Other'];
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (string)($_POST['id'] ?? '');

    if ($action === 'save') {
        $row = [
            'label'     => in_array($_POST['label'] ?? '', $labels, true) ? $_POST['label'] : 'Home',
            'recipient' => trim($_POST['recipient'] ?? ''),
            'phone'     => trim($_POST['phone'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'city'      => trim($_POST['city'] ?? ''),
            'postal'    => trim($_POST['postal'] ?? ''),
        ];
        if ($row['recipient'] === '' || $row['address'] === '' || $row['city'] === '') {
            $err = 'Recipient, street address and city are required.';
        } elseif ($row['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $row['phone'])) {
            $err = 'Please enter a valid phone number.';
        } elseif ($row['postal'] !== '' && !preg_match('/^[0-9]{4,10}$/', $row['postal'])) {
            $err = 'Postal code must contain digits only.';
        } else {
            if ($id !== '') {
                foreach ($addresses as &$a) {
                    if ((string)$a['id'] === $id) { $a = array_merge($a, $row); }
                }
                unset($a);
                $msg = 'Address updated.';
            } else {
                $row['id'] = time() . mt_rand(10, 99);
                $row['default'] = empty($addresses);
                $addresses[] = $row;
                $msg = 'Address added.';
            }
        }
    }

    if ($action === 'default') {
        foreach ($addresses as &$a) { $a['default'] = ((string)$a['id'] === $id); }
        unset($a);
        $msg = 'Default address changed.';
    }

    if ($action === 'delete') {
        $addresses = array_values(array_filter($addresses, fn($a) => (string)$a['id'] !== $id));
        if ($addresses && !array_filter($addresses, fn($a) => $a['default'])) $addresses[0]['default'] = true;
        $msg = 'Address deleted.';
    }

    // Keep profile address in sync with the default one
    foreach ($addresses as $a) {
        if ($a['default']) {
            $_SESSION['user'] = array_merge($user, ['address' => $a['address'] . ', ' . $a['city']]);
        }
    }
    if ($err) { $editing = $_POST; }
}

// Edit mode (?edit=ID)
$edit = null;
if (!empty($_GET['edit'])) {
    foreach ($addresses as $a) if ((string)$a['id'] === (string)$_GET['edit']) $edit = $a;
}
if (!$edit && !empty($editing)) $edit = $editing;
$f = fn($k, $d = '') => e($edit[$k] ?? $d);

$avatar = $user['avatar'] ?: 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#FFE9A3"/><circle cx="50" cy="38" r="18" fill="#F2A900"/><path d="M14 100c0-22 16-34 36-34s36 12 36 34z" fill="#F2A900"/></svg>');

$menu = [
    ['profile',  'Profile',   'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z', 'profile.php'],
    ['orders',   'My Orders', 'M6 2l-2 4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4H6zm0 4h12M9 10a3 3 0 0 0 6 0', 'orders.php'],
    ['wishlist', 'Wishlist',  'M12 21s-7-4.4-9.3-9C1.1 8.7 3 5 6.5 5c2 0 3.5 1.1 5.5 3 2-1.9 3.5-3 5.5-3C21 5 22.9 8.7 21.3 12c-2.3 4.6-9.3 9-9.3 9z', 'wishlist.php'],
    ['reviews',  'Reviews',   'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.2 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3z', 'reviews.php'],
    ['address',  'Address',   'M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'address.php'],
    ['settings', 'Settings',  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14.5 3h-4l-.4 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2l.4 2.6h4l.4-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z', 'profile.php'],
    ['logout',   'Logout',    'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9', 'logout.php'],
];
$active = 'address';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Addresses – Kabupaten Sukabumi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#FFC41F;--yellow-d:#F2A900;--cream:#FFF8E1;--cream-2:#FFFBEF;--ink:#1F2933;--muted:#6B7280;--line:#EEE7CF;--white:#fff;--ok:#1D6B3A;--bad:#B42318}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',system-ui,sans-serif;color:var(--ink);background:linear-gradient(180deg,var(--cream-2) 0%,#FFF3C4 100%) fixed;min-height:100vh}
a{color:inherit;text-decoration:none}
.topbar{background:var(--white);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
.topbar .in{max-width:1100px;margin:auto;display:flex;align-items:center;gap:28px;padding:10px 20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;line-height:1.1}
.brand svg{width:38px;height:38px}
.nav{display:flex;gap:22px;margin-left:auto;font-size:13px;font-weight:500}
.nav a{padding:6px 0;border-bottom:2px solid transparent}
.nav a.on{color:var(--yellow-d);border-color:var(--yellow-d)}
.icons{display:flex;gap:16px}
.icons svg{width:20px;height:20px;stroke:var(--ink);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.wrap{max-width:1100px;margin:28px auto;padding:0 20px}
.card{background:var(--white);border-radius:18px;box-shadow:0 6px 24px rgba(180,140,20,.12);display:grid;grid-template-columns:260px 1fr;overflow:hidden}
.side{padding:28px 18px;border-right:1px solid var(--line);background:var(--cream-2)}
.me{text-align:center;margin-bottom:22px}
.me img{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--yellow);background:#fff}
.me h3{font-size:15px;margin-top:10px}
.me small{color:var(--muted);font-size:11px}
.menu{list-style:none;display:grid;gap:4px}
.menu a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;font-size:13.5px;font-weight:500;color:#374151}
.menu a:hover{background:#FFF1BF}
.menu a.on{background:var(--yellow);color:var(--ink);font-weight:600}
.menu svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.main{padding:32px 36px}
h2{font-size:20px;margin-bottom:18px}
h3.sub{font-size:16px;margin:30px 0 14px;padding-top:24px;border-top:1px solid var(--line)}
.flash{border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px}
.flash.ok{background:#E8F7EC;color:var(--ok)}
.flash.bad{background:#FDECEA;color:var(--bad)}
.addr{border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:12px;background:var(--cream-2)}
.addr.def{border-color:var(--yellow-d);background:#FFF9E0}
.addr-top{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.addr-top b{font-size:14.5px}
.badge{background:var(--yellow);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600}
.addr p{font-size:13.5px;line-height:1.7;color:#374151}
.addr .who{color:var(--muted);font-size:12.5px}
.acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.acts form{display:inline}
.sm{background:#fff;border:1px solid var(--line);border-radius:8px;padding:5px 12px;font:inherit;font-size:12px;cursor:pointer;color:var(--ink);display:inline-block}
.sm:hover{background:#FFF1BF}
.sm.red{color:var(--bad)}
.sm.red:hover{background:#FDECEA}
.empty{border:1px dashed var(--yellow-d);border-radius:12px;padding:22px;text-align:center;color:var(--muted);font-size:13.5px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}
.input{border:1px solid var(--line);border-radius:10px;padding:10px 14px;background:var(--cream-2)}
.input input,.input select,.input textarea{border:0;background:transparent;font:inherit;font-size:14px;width:100%;outline:none;color:var(--ink);resize:vertical}
.input:focus-within{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn{display:block;width:100%;margin-top:8px;background:var(--yellow);border:0;border-radius:10px;padding:12px;font:inherit;font-weight:600;font-size:14px;cursor:pointer;text-align:center}
.btn:hover{background:var(--yellow-d)}
.btn.ghost{background:#fff;border:1px solid var(--line)}
@media (max-width:820px){
  .card{grid-template-columns:1fr}
  .side{border-right:0;border-bottom:1px solid var(--line)}
  .menu{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
  .nav{display:none}.icons{margin-left:auto}
  .main{padding:24px 18px}.row2{grid-template-columns:1fr}
}
</style>
</head>
<body>

<header class="topbar">
  <div class="in">
    <a class="brand" href="index.php">
      <svg viewBox="0 0 40 40"><path d="M20 2l15 5v13c0 9-7 15-15 18C12 35 5 29 5 20V7z" fill="#FFC41F" stroke="#1F2933" stroke-width="2"/><path d="M13 20h14M20 12v16" stroke="#1F2933" stroke-width="2.5" stroke-linecap="round"/></svg>
      <span>Kabupaten<br>Sukabumi</span>
    </a>
    <nav class="nav">
      <a href="index.php">Home</a><a href="culinary.php" class="on">Culinary</a><a href="tourism.php">Tourism</a><a href="about.php">About</a>
    </nav>
    
  </div>
</header>

<main class="wrap">
  <div class="card">

    <aside class="side">
      <div class="me">
        <img src="<?= e($avatar) ?>" alt="<?= e($user['name']) ?>">
        <h3><?= e($user['name']) ?></h3>
        <small><?= e($user['email']) ?></small>
      </div>
      <ul class="menu">
        <?php foreach ($menu as [$key, $label, $path, $href]): ?>
          <li><a href="<?= $href ?>" class="<?= $key === $active ? 'on' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="<?= $path ?>"/></svg><?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <section class="main">
      <?php if ($msg): ?><div class="flash ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="flash bad"><?= e($err) ?></div><?php endif; ?>

      <h2>Saved Addresses</h2>

      <?php if (!$addresses): ?>
        <div class="empty">No saved addresses yet. Add one below to speed up checkout.</div>
      <?php endif; ?>

      <?php foreach ($addresses as $a): ?>
        <article class="addr <?= $a['default'] ? 'def' : '' ?>">
          <div class="addr-top">
            <b><?= e($a['label']) ?></b>
            <?php if ($a['default']): ?><span class="badge">Default</span><?php endif; ?>
          </div>
          <p class="who"><?= e($a['recipient']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?></p>
          <p><?= e($a['address']) ?>, <?= e($a['city']) ?><?= $a['postal'] ? ' ' . e($a['postal']) : '' ?></p>
          <div class="acts">
            <a class="sm" href="address.php?edit=<?= e($a['id']) ?>#form">Edit</a>
            <?php if (!$a['default']): ?>
              <form method="post">
                <input type="hidden" name="action" value="default">
                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <button class="sm" type="submit">Set as default</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this address?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <button class="sm red" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>

      <h3 class="sub" id="form"><?= !empty($edit['id']) ? 'Edit Address' : 'Add New Address' ?></h3>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $f('id') ?>">

        <div class="row2">
          <div class="field">
            <label for="label">Label</label>
            <div class="input">
              <select id="label" name="label">
                <?php foreach ($labels as $l): ?>
                  <option <?= (($edit['label'] ?? 'Home') === $l) ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="recipient">Recipient name</label>
            <div class="input"><input id="recipient" name="recipient" value="<?= $f('recipient', $user['name']) ?>" required></div>
          </div>
        </div>

        <div class="field">
          <label for="phone">Phone</label>
          <div class="input"><input id="phone" name="phone" value="<?= $f('phone', $user['phone']) ?>"></div>
        </div>
        <div class="field">
          <label for="address">Street address</label>
          <div class="input"><textarea id="address" name="address" rows="2" required><?= $f('address') ?></textarea></div>
        </div>
        <div class="row2">
          <div class="field">
            <label for="city">City</label>
            <div class="input"><input id="city" name="city" value="<?= $f('city') ?>" required></div>
          </div>
          <div class="field">
            <label for="postal">Postal code</label>
            <div class="input"><input id="postal" name="postal" inputmode="numeric" value="<?= $f('postal') ?>"></div>
          </div>
        </div>

        <button class="btn" type="submit"><?= !empty($edit['id']) ? 'Save Changes' : 'Add Address' ?></button>
        <?php if (!empty($edit['id'])): ?><a class="btn ghost" href="address.php">Cancel</a><?php endif; ?>
      </form>
    </section>

  </div>
</main>
</body>
</html>