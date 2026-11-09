<?php
session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$r = $_SESSION['reset'] ?? null;

// Is the link valid? (replace with a database lookup of the hashed token)
$valid = $r && is_string($token) && $token !== ''
      && hash_equals($r['hash'], hash('sha256', $token))
      && time() < $r['expires'];

$done = false; $error = '';

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['password'] ?? '';
    $con = $_POST['confirm'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) $error = 'Your session expired. Please try again.';
    elseif (strlen($new) < 6)  $error = 'Password must be at least 6 characters.';
    elseif ($new !== $con)     $error = 'The two passwords do not match.';
    else {
        // Save the new password (replace with: UPDATE users SET password = ? WHERE email = ?)
        $_SESSION['user'] = array_merge(is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [], [
            'email'    => $r['email'],
            'password' => password_hash($new, PASSWORD_DEFAULT),
        ]);
        unset($_SESSION['reset']);          // the link can be used only once
        session_regenerate_id(true);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password – Kabupaten Sukabumi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#FFC41F;--yellow-d:#F2A900;--cream-2:#FFFBEF;--ink:#1F2933;--muted:#6B7280;--line:#EEE7CF;--ok:#1D6B3A;--bad:#B42318}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',system-ui,sans-serif;color:var(--ink);background:linear-gradient(180deg,var(--cream-2) 0%,#FFF3C4 100%) fixed;min-height:100vh;display:grid;place-items:center;padding:24px}
a{color:inherit;text-decoration:none}
.card{width:100%;max-width:440px;background:#fff;border-radius:20px;box-shadow:0 10px 40px rgba(180,140,20,.18);padding:36px 32px}
.brand{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;line-height:1.1;margin-bottom:22px}
.brand svg{width:40px;height:40px}
h1{font-size:22px;margin-bottom:6px}
.sub{color:var(--muted);font-size:13.5px;line-height:1.7;margin-bottom:22px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}
.input{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:10px;padding:11px 14px;background:var(--cream-2)}
.input input{border:0;background:transparent;font:inherit;font-size:14px;width:100%;outline:none;color:var(--ink)}
.input:focus-within{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.show{background:none;border:0;font:inherit;font-size:12px;color:var(--yellow-d);font-weight:600;cursor:pointer;flex:none}
.btn{display:block;width:100%;background:var(--yellow);border:0;border-radius:10px;padding:12px;font:inherit;font-weight:600;font-size:14px;cursor:pointer;text-align:center}
.btn:hover{background:var(--yellow-d)}
.flash{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13.5px;line-height:1.6}
.flash.ok{background:#E8F7EC;color:var(--ok)}
.flash.bad{background:#FDECEA;color:var(--bad)}
.back{display:block;text-align:center;margin-top:18px;font-size:13.5px;color:var(--muted)}
.back a{color:var(--yellow-d);font-weight:600}
</style>
</head>
<body>
<main class="card">
  <a class="brand" href="index.php">
    <svg viewBox="0 0 40 40"><path d="M20 2l15 5v13c0 9-7 15-15 18C12 35 5 29 5 20V7z" fill="#FFC41F" stroke="#1F2933" stroke-width="2"/><path d="M13 20h14M20 12v16" stroke="#1F2933" stroke-width="2.5" stroke-linecap="round"/></svg>
    <span>Kabupaten<br>Sukabumi</span>
  </a>

  <?php if ($done): ?>
    <h1>Password updated</h1>
    <div class="flash ok" role="status" style="margin-top:14px">Your password has been changed. You can now log in with the new password.</div>
    <a class="btn" href="login.php">Go to login</a>

  <?php elseif (!$valid): ?>
    <h1>Link not valid</h1>
    <div class="flash bad" role="alert" style="margin-top:14px">This reset link is invalid or has expired. Please request a new one.</div>
    <a class="btn" href="forgot-password.php">Request a new link</a>

  <?php else: ?>
    <h1>Choose a new password</h1>
    <p class="sub">For <b><?= e($r['email']) ?></b>. Use at least 6 characters.</p>
    <?php if ($error): ?><div class="flash bad" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">New password</label>
        <div class="input">
          <input id="password" type="password" name="password" minlength="6" autocomplete="new-password" required autofocus>
          <button class="show" type="button" data-for="password">Show</button>
        </div>
      </div>
      <div class="field">
        <label for="confirm">Confirm new password</label>
        <div class="input">
          <input id="confirm" type="password" name="confirm" minlength="6" autocomplete="new-password" required>
          <button class="show" type="button" data-for="confirm">Show</button>
        </div>
      </div>
      <button class="btn" type="submit">Update Password</button>
    </form>
  <?php endif; ?>

  <p class="back"><a href="login.php">Back to login</a></p>
</main>

<script>
document.querySelectorAll('.show').forEach(b => b.addEventListener('click', () => {
  const i = document.getElementById(b.dataset.for), t = i.type === 'password';
  i.type = t ? 'text' : 'password'; b.textContent = t ? 'Hide' : 'Show';
}));
</script>
</body>
</html>