<?php
session_start();

// ---- Demo data (replace with your database query) ----
$defaults = [
    'name'     => 'Myat Noe Wai',
    'email'    => 'myatnoewai@gmail.com',
    'phone'    => '+62 812 3456 7890',
    'address'  => 'Jl. Melati No. 12, Sukabumi, Jawa Barat, Indonesia',
    'avatar'   => '',
    'password' => password_hash('123456', PASSWORD_DEFAULT),
];
// Merge so that old session data (from earlier versions) never causes "Undefined array key"
$user = array_merge($defaults, is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : []);
$_SESSION['user'] = $user;

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$msg = ''; $err = '';
$uploadDir = __DIR__ . '/uploads/avatars/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '') {
            $err = 'Full name is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            $user['name']    = $name;
            $user['email']   = $email;
            $user['phone']   = trim($_POST['phone'] ?? '');
            $user['address'] = trim($_POST['address'] ?? '');

            // Profile photo upload (JPG, PNG, WEBP, max 2 MB)
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $f = $_FILES['photo'];
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $err = 'Upload failed. Please try again.';
                } elseif (!isset($allowed[$mime])) {
                    $err = 'Photo must be a JPG, PNG or WEBP image.';
                } elseif ($f['size'] > 2 * 1024 * 1024) {
                    $err = 'Photo must be 2 MB or smaller.';
                } else {
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $file = 'u_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                    if (move_uploaded_file($f['tmp_name'], $uploadDir . $file)) {
                        $user['avatar'] = 'uploads/avatars/' . $file;
                    } else {
                        $err = 'Could not save the photo.';
                    }
                }
            }
            if (!$err) { $_SESSION['user'] = $user; $msg = 'Profile updated.'; }
        }
    }

    if ($action === 'password') {
        $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $con = $_POST['confirm'] ?? '';
        if (!password_verify($cur, $user['password'])) $err = 'Current password is incorrect.';
        elseif (strlen($new) < 6)                       $err = 'New password must be at least 6 characters.';
        elseif ($new !== $con)                          $err = 'New passwords do not match.';
        else {
            $user['password'] = password_hash($new, PASSWORD_DEFAULT);
            $_SESSION['user'] = $user;
            $msg = 'Password updated.';
        }
    }
}

$avatar = $user['avatar'] ?: 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#FFE9A3"/><circle cx="50" cy="38" r="18" fill="#F2A900"/><path d="M14 100c0-22 16-34 36-34s36 12 36 34z" fill="#F2A900"/></svg>');

$menu = [
    ['profile',  'Profile',   'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z', 'profile.php'],
    ['orders',   'My Orders', 'M6 2l-2 4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4H6zm0 4h12M9 10a3 3 0 0 0 6 0', 'orders.php'],
    ['wishlist', 'Wishlist',  'M12 21s-7-4.4-9.3-9C1.1 8.7 3 5 6.5 5c2 0 3.5 1.1 5.5 3 2-1.9 3.5-3 5.5-3C21 5 22.9 8.7 21.3 12c-2.3 4.6-9.3 9-9.3 9z', 'wishlist.php'],
    ['reviews',  'Reviews',   'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.2 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3z', 'reviews.php'],
    ['address',  'Address',   'M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'address.php'],
    ['settings', 'Settings',  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14.5 3h-4l-.4 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2l.4 2.6h4l.4-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z', 'settings.php'],
    ['logout',   'Logout',    'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9', 'logout.php'],
];
$active = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile – Kabupaten Sukabumi</title>
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
h3.sub{font-size:16px;margin:34px 0 14px;padding-top:26px;border-top:1px solid var(--line)}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}
.field label span{font-size:11px;opacity:.8}
.input{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:10px;padding:10px 14px;background:var(--cream-2)}
.input svg{width:16px;height:16px;fill:none;stroke:var(--muted);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.input input,.input textarea{border:0;background:transparent;font:inherit;font-size:14px;width:100%;outline:none;color:var(--ink);resize:vertical}
.input:focus-within{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.input[readonly-wrap]{opacity:.7}
.photo{display:flex;align-items:center;gap:16px;border:1px dashed var(--yellow-d);border-radius:12px;padding:14px;background:var(--cream-2)}
.photo img{width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--yellow);background:#fff}
.photo input[type=file]{font:inherit;font-size:13px;max-width:100%}
.photo input[type=file]::file-selector-button{background:var(--yellow);border:0;border-radius:8px;padding:8px 14px;font:inherit;font-weight:600;font-size:13px;cursor:pointer;margin-right:10px}
.btn{display:block;width:100%;margin-top:8px;background:var(--yellow);border:0;border-radius:10px;padding:12px;font:inherit;font-weight:600;font-size:14px;cursor:pointer}
.btn:hover{background:var(--yellow-d)}
.flash{border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px}
.flash.ok{background:#E8F7EC;color:var(--ok)}
.flash.bad{background:#FDECEA;color:var(--bad)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:820px){
  .card{grid-template-columns:1fr}
  .side{border-right:0;border-bottom:1px solid var(--line)}
  .menu{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
  .nav{display:none}.icons{margin-left:auto}
  .main{padding:24px 18px}.row2{grid-template-columns:1fr}
}

/* Header icon buttons, search bar and account menu */
.ib{background:none;border:0;padding:6px;border-radius:8px;cursor:pointer;display:flex;align-items:center;color:var(--ink)}
.ib:hover,.ib[aria-expanded="true"]{background:#FFF1BF}
.ib:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
.dd{position:relative}
.ddm{position:absolute;right:0;top:calc(100% + 8px);min-width:170px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(120,90,10,.18);padding:6px;z-index:30}
.ddm[hidden],.searchbar[hidden]{display:none}
.ddm a{display:block;padding:9px 12px;border-radius:8px;font-size:13.5px;font-weight:500}
.ddm a:hover{background:#FFF1BF}
.ddm a.out{color:var(--bad);border-top:1px solid var(--line);border-radius:0 0 8px 8px;margin-top:4px}
.searchbar{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:59px;z-index:9}
.sb-in{max-width:1100px;margin:auto;padding:10px 20px;display:flex;gap:10px}
.sb-in input{flex:1;border:1px solid var(--line);background:var(--cream-2);border-radius:10px;padding:10px 14px;font:inherit;font-size:14px;outline:none}
.sb-in input:focus{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.sb-in button{background:var(--yellow);border:0;border-radius:10px;padding:0 20px;font:inherit;font-weight:600;font-size:14px;cursor:pointer}
.sb-in button:hover{background:var(--yellow-d)}
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
    <div class="icons">
      
      
        <div class="ddm" id="userMenu" hidden>
          <a href="profile.php">My Profile</a>
          <a href="orders.php">My Orders</a>
          <a href="settings.php">Settings</a>
          <a href="logout.php" class="out">Logout</a>
        </div>
      </div>
    </div>
  </div>
</header>
<form class="searchbar" id="searchBar" action="culinary.php" method="get" role="search" hidden>
  <div class="sb-in">
    <input type="search" name="q" id="q" placeholder="Search food, restaurant, or location..." aria-label="Search">
    <button type="submit">Search</button>
  </div>
</form>


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

      <h2>Edit Profile</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="profile">

        <div class="field">
          <label for="name">Full Name</label>
          <div class="input"><input id="name" name="name" value="<?= e($user['name']) ?>" required></div>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <div class="input"><input id="email" type="email" name="email" value="<?= e($user['email']) ?>" required></div>
        </div>
        <div class="field">
          <label for="phone">Phone</label>
          <div class="input"><input id="phone" name="phone" value="<?= e($user['phone']) ?>"></div>
        </div>
        <div class="field">
          <label for="address">Address</label>
          <div class="input"><textarea id="address" name="address" rows="2"><?= e($user['address']) ?></textarea></div>
        </div>
        <div class="field">
          <label>Profile Photo <span>(JPG, PNG or WEBP, max 2 MB)</span></label>
          <div class="photo">
            <img id="preview" src="<?= e($avatar) ?>" alt="Preview">
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp">
          </div>
        </div>
        <button class="btn" type="submit">Save Changes</button>
      </form>

      <h3 class="sub" id="password">Change Password</h3>
      <form method="post">
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label for="current">Current Password</label>
          <div class="input"><input id="current" type="password" name="current" autocomplete="current-password" required></div>
        </div>
        <div class="row2">
          <div class="field">
            <label for="new">New Password</label>
            <div class="input"><input id="new" type="password" name="new" minlength="6" autocomplete="new-password" required></div>
          </div>
          <div class="field">
            <label for="confirm">Confirm New Password</label>
            <div class="input"><input id="confirm" type="password" name="confirm" minlength="6" autocomplete="new-password" required></div>
          </div>
        </div>
        <button class="btn" type="submit">Update Password</button>
      </form>
    </section>

  </div>
</main>

<script>
// Live preview of the chosen photo
document.getElementById('photo').addEventListener('change', function () {
  const f = this.files[0];
  if (!f) return;
  if (f.size > 2 * 1024 * 1024) { alert('Photo must be 2 MB or smaller.'); this.value = ''; return; }
  document.getElementById('preview').src = URL.createObjectURL(f);
});
</script>

<script>
// Header: search bar + account menu
(function () {
  const btnS = document.getElementById('btnSearch'), bar = document.getElementById('searchBar');
  const btnU = document.getElementById('btnUser'),   menu = document.getElementById('userMenu');
  function toggle(btn, el, open) {
    el.hidden = !open; btn.setAttribute('aria-expanded', open);
  }
  btnS.addEventListener('click', () => {
    const open = bar.hidden; toggle(btnS, bar, open); toggle(btnU, menu, false);
    if (open) document.getElementById('q').focus();
  });
  btnU.addEventListener('click', e => {
    e.stopPropagation();
    toggle(btnU, menu, menu.hidden); toggle(btnS, bar, false);
  });
  document.addEventListener('click', e => { if (!menu.contains(e.target)) toggle(btnU, menu, false); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { toggle(btnU, menu, false); toggle(btnS, bar, false); }
  });
})();
</script>
</body>
</html>