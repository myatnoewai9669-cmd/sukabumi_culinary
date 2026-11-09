<?php
session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Contact details (replace with your real information) ----
$info = [
    'address' => 'Jl. Contoh No. 1, Kabupaten Sukabumi, Jawa Barat, Indonesia',
    'phone'   => '+62 813-1862-945',
    'whatsapp'=> '+62 813-1862-945',
    'email'   => 'myatnoewai9669@gmail.com',
    'hours'   => 'Monday to Friday, 08:00 to 16:00',
    'map'     => 'https://www.google.com/maps/search/?api=1&query=Kabupaten+Sukabumi',
];

$subjects = ['General question', 'Order or delivery', 'Restaurant or business listing', 'Event or festival', 'Feedback', 'Other'];

// Prefill from the logged-in user (shared with profile.php session)
$u = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$old = [
    'name'    => $u['name']  ?? '',
    'email'   => $u['email'] ?? '',
    'phone'   => $u['phone'] ?? '',
    'subject' => '',
    'message' => '',
];

// CSRF token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$ok = false; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!empty($_POST['website'])) {
        // honeypot field filled in: silently pretend success (bot)
        $ok = true; $old = array_map(fn($v) => '', $old);
    } else {
        if (mb_strlen($old['name']) < 2)                          $errors['name']    = 'Please enter your name.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors['email']   = 'Please enter a valid email address.';
        if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $old['phone']))
                                                                  $errors['phone']   = 'Please enter a valid phone number.';
        if (!in_array($old['subject'], $subjects, true))          $errors['subject'] = 'Please choose a subject.';
        if (mb_strlen($old['message']) < 10)                      $errors['message'] = 'Please write at least 10 characters.';
        if (mb_strlen($old['message']) > 1000)                    $errors['message'] = 'Message must be 1000 characters or fewer.';

        // simple rate limit: one message every 30 seconds per visitor
        if (!$errors && isset($_SESSION['last_contact']) && time() - $_SESSION['last_contact'] < 30) {
            $errors[] = 'Please wait a moment before sending another message.';
        }

        if (!$errors) {
            $entry = $old + ['date' => date('Y-m-d H:i:s')];

            // 1) Keep a copy in a log file (make sure the "data" folder is not public in production)
            $dir = __DIR__ . '/data';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/contact_messages.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

            // 2) Optional: send an email (needs a configured mail server)
            // mail($info['email'], 'Contact: ' . $old['subject'], $old['message'], 'Reply-To: ' . $old['email']);

            // 3) Optional: insert into your database here

            $_SESSION['last_contact'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $ok = true;
            $old = array_merge($old, ['subject' => '', 'message' => '']);
        }
    }
}
$err = fn($k) => isset($errors[$k]) ? '<small class="fe">' . e($errors[$k]) . '</small>' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contact – Kabupaten Sukabumi</title>
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
.wrap{max-width:1100px;margin:0 auto;padding:0 20px}
.hero{padding:44px 0 26px}
.hero h1{font-size:34px;line-height:1.2;max-width:560px}
.hero p{color:var(--muted);margin-top:10px;max-width:520px;font-size:14.5px;line-height:1.7}
.grid{display:grid;grid-template-columns:1fr 1.25fr;gap:24px;margin-bottom:40px}
.box{background:var(--white);border-radius:18px;box-shadow:0 6px 24px rgba(180,140,20,.12);padding:28px}
h2{font-size:18px;margin-bottom:18px}
.item{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--line)}
.item:last-of-type{border-bottom:0}
.ic{flex:none;width:40px;height:40px;border-radius:12px;background:#FFF1BF;display:grid;place-items:center}
.ic svg{width:20px;height:20px;fill:none;stroke:var(--ink);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.item b{display:block;font-size:13.5px}
.item span,.item a{font-size:13.5px;color:#374151;line-height:1.6}
.item a:hover{color:var(--yellow-d)}
.mapbtn{display:block;margin-top:14px;text-align:center;border:1px solid var(--yellow-d);border-radius:10px;padding:10px;font-weight:600;font-size:13.5px}
.mapbtn:hover{background:#FFF1BF}
.flash{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13.5px}
.flash.ok{background:#E8F7EC;color:var(--ok)}
.flash.bad{background:#FDECEA;color:var(--bad)}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}
.input{border:1px solid var(--line);border-radius:10px;padding:10px 14px;background:var(--cream-2)}
.input.bad{border-color:var(--bad)}
.input input,.input select,.input textarea{border:0;background:transparent;font:inherit;font-size:14px;width:100%;outline:none;color:var(--ink);resize:vertical}
.input:focus-within{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.fe{display:block;color:var(--bad);font-size:12px;margin-top:5px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cnt{text-align:right;font-size:11.5px;color:var(--muted);margin-top:4px}
.btn{display:block;width:100%;margin-top:6px;background:var(--yellow);border:0;border-radius:10px;padding:12px;font:inherit;font-weight:600;font-size:14px;cursor:pointer}
.btn:hover{background:var(--yellow-d)}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
footer{border-top:1px solid var(--line);background:#fff;padding:18px 20px;text-align:center;color:var(--muted);font-size:12.5px}
@media (max-width:820px){
  .nav{display:none}
  .hero h1{font-size:26px}
  .grid{grid-template-columns:1fr}
  .row2{grid-template-columns:1fr}
  .box{padding:22px 18px}
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
      <a href="index.php">Home</a>
      <a href="culinary.php">Culinary</a>
      <a href="tourism.php">Tourism</a>
      <a href="about.php">About</a>
      <a href="contact.php" class="on">Contact</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero">
    <h1>We would love to hear from you</h1>
    <p>Questions about food, orders, events or listing your business? Send us a message and we will reply as soon as we can.</p>
  </section>

  <div class="grid">

    <aside class="box">
      <h2>Contact information</h2>

      <div class="item">
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg></div>
        <div><b>Address</b><span><?= e($info['address']) ?></span></div>
      </div>
      <div class="item">
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg></div>
        <div><b>Phone</b><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $info['phone'])) ?>"><?= e($info['phone']) ?></a></div>
      </div>
      <div class="item">
        <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.5 7.2L3 21l1.8-5.5A8 8 0 1 1 21 12z"/></svg></div>
        <div><b>WhatsApp</b><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $info['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($info['whatsapp']) ?></a></div>
      </div>
      <div class="item">
        <div class="ic"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></div>
        <div><b>Email</b><a href="mailto:<?= e($info['email']) ?>"><?= e($info['email']) ?></a></div>
      </div>
      <div class="item">
        <div class="ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
        <div><b>Opening hours</b><span><?= e($info['hours']) ?></span></div>
      </div>

      <a class="mapbtn" href="<?= e($info['map']) ?>" target="_blank" rel="noopener">Open in Maps</a>
    </aside>

    <section class="box">
      <h2>Send a message</h2>

      <?php if ($ok): ?>
        <div class="flash ok" role="status">Thank you. Your message has been sent and we will reply by email.</div>
      <?php endif; ?>
      <?php foreach ($errors as $k => $m): if (is_int($k)): ?>
        <div class="flash bad" role="alert"><?= e($m) ?></div>
      <?php endif; endforeach; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="row2">
          <div class="field">
            <label for="name">Full name</label>
            <div class="input <?= isset($errors['name']) ? 'bad' : '' ?>"><input id="name" name="name" value="<?= e($old['name']) ?>" required></div>
            <?= $err('name') ?>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <div class="input <?= isset($errors['email']) ? 'bad' : '' ?>"><input id="email" type="email" name="email" value="<?= e($old['email']) ?>" required></div>
            <?= $err('email') ?>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label for="phone">Phone (optional)</label>
            <div class="input <?= isset($errors['phone']) ? 'bad' : '' ?>"><input id="phone" name="phone" value="<?= e($old['phone']) ?>"></div>
            <?= $err('phone') ?>
          </div>
          <div class="field">
            <label for="subject">Subject</label>
            <div class="input <?= isset($errors['subject']) ? 'bad' : '' ?>">
              <select id="subject" name="subject" required>
                <option value="">Choose a subject</option>
                <?php foreach ($subjects as $s): ?>
                  <option <?= $old['subject'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?= $err('subject') ?>
          </div>
        </div>

        <div class="field">
          <label for="message">Message</label>
          <div class="input <?= isset($errors['message']) ? 'bad' : '' ?>">
            <textarea id="message" name="message" rows="6" maxlength="1000" required><?= e($old['message']) ?></textarea>
          </div>
          <?= $err('message') ?>
          <div class="cnt"><span id="n"><?= mb_strlen($old['message']) ?></span>/1000</div>
        </div>

        <button class="btn" type="submit">Send Message</button>
      </form>
    </section>

  </div>
</main>

<footer>Kabupaten Sukabumi · Taste, Explore, Enjoy</footer>

<script>
const m = document.getElementById('message'), n = document.getElementById('n');
m.addEventListener('input', () => n.textContent = m.value.length);
</script>
</body>
</html>