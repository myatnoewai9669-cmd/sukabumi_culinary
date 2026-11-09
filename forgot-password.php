<?php
/**
 * forgot-password.php
 * 4 steps: email -> 6-digit code -> new password -> done.
 *
 * Step 2 uses a glowing "ember" code field: six slots that light up as you type,
 * auto-advance, paste support, auto-submit, shake on a wrong code, and a resend timer.
 *
 * DEMO MODE (no mail server needed) - see FP_DEMO_MODE below.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

const FP_CODE_LENGTH    = 6;
const FP_CODE_MINUTES   = 10;
const FP_MAX_TRIES      = 5;
const FP_CODES_PER_HOUR = 3;
const FP_RESEND_SECONDS = 30;

/**
 * Where to show the code on screen as a fake "Mail" notification (with a Fill button):
 *   'off'   : never. Real emails only (needs mail.php and mail_config.php).
 *   'local' : only when you open the site on your own computer (localhost). SAFE. Default.
 *   'on'    : on every visitor's screen, also on the live site. ONLY for a presentation/demo with test accounts!
 *             WARNING: with 'on', anyone who knows a registered email can reset that account's password,
 *             because the code is shown to them. Never use 'on' on a real website.
 */
const FP_DEMO_MODE = 'local';

// ---------- Helpers ----------
function fp_csrf() {
    if (empty($_SESSION['fp_csrf'])) {
        $_SESSION['fp_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['fp_csrf'];
}
function fp_csrf_check() {
    if (!hash_equals($_SESSION['fp_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Session expired. Please go back and refresh the page.');
    }
}
function fp_pick_col($row, $options) {
    foreach ($options as $o) {
        if (array_key_exists($o, $row)) { return $o; }
    }
    return null;
}
function fp_clear() {
    foreach (['fp_email', 'fp_verified', 'fp_dev_code', 'fp_info', 'fp_sent_at'] as $k) {
        unset($_SESSION[$k]);
    }
}
// Optional: real emails (only if you add mail.php and mail_config.php later)
function fp_mail_ready() {
    return is_file(__DIR__ . '/mail.php') && is_file(__DIR__ . '/mail_config.php');
}

$is_local  = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$show_code = (FP_DEMO_MODE === 'on') || (FP_DEMO_MODE === 'local' && $is_local);

// Create a new code for this email (only if the account exists and the limit allows)
function fp_issue_code(PDO $pdo, $email, $show_code) {
    unset($_SESSION['fp_dev_code']);

    $st = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user) { return; }   // do nothing, but the page looks the same

    $st = $pdo->prepare("SELECT COUNT(*) FROM password_reset_codes
                         WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
    $st->execute([$user['id']]);
    if ((int)$st->fetchColumn() >= FP_CODES_PER_HOUR) { return; }

    $pdo->prepare("DELETE FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL")
        ->execute([$user['id']]);

    $code = str_pad((string)random_int(0, 10 ** FP_CODE_LENGTH - 1), FP_CODE_LENGTH, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO password_reset_codes (user_id, code_hash, expires_at)
                   VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . FP_CODE_MINUTES . " MINUTE))")
        ->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT)]);

    if (fp_mail_ready()) {
        require_once 'mail.php';
        $name_col = fp_pick_col($user, ['name', 'full_name', 'username']);
        $name     = $name_col ? $user[$name_col] : '';
        send_mail($email, 'Your reset code: ' . $code,
            "Hello $name,\n\nYour password reset code is:\n\n    $code\n\nThis code works for "
            . FP_CODE_MINUTES . " minutes. If you did not request it, ignore this email.\n");
    }

    // Demo mode: show the code on screen
    if ($show_code) { $_SESSION['fp_dev_code'] = $code; }
}

// Start over
if (isset($_GET['restart'])) {
    fp_clear();
    header('Location: forgot-password.php');
    exit;
}

$error  = '';
$done   = false;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fp_csrf_check();

    // ---- Step 1: email ----
    if ($action === 'email') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            fp_clear();
            $_SESSION['fp_email'] = $email;
            fp_issue_code($pdo, $email, $show_code);
            $_SESSION['fp_sent_at'] = time();
            header('Location: forgot-password.php');
            exit;
        }
    }

    // ---- Resend the code ----
    elseif ($action === 'resend' && !empty($_SESSION['fp_email'])) {
        $wait = FP_RESEND_SECONDS - (time() - (int)($_SESSION['fp_sent_at'] ?? 0));
        if ($wait > 0) {
            $error = 'Please wait ' . $wait . ' second' . ($wait === 1 ? '' : 's') . ' before asking for a new code.';
        } else {
            unset($_SESSION['fp_verified']);
            fp_issue_code($pdo, $_SESSION['fp_email'], $show_code);
            $_SESSION['fp_sent_at'] = time();
            $_SESSION['fp_info'] = 'If the email is registered, a new code has been sent.';
            header('Location: forgot-password.php');
            exit;
        }
    }

    // ---- Step 2: verify the code ----
    elseif ($action === 'otp' && !empty($_SESSION['fp_email'])) {
        $entered = preg_replace('/\D/', '', implode('', (array)($_POST['otp'] ?? [])));

        if (strlen($entered) !== FP_CODE_LENGTH) {
            $error = 'Please enter the ' . FP_CODE_LENGTH . '-digit code.';
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare("SELECT c.id, c.user_id, c.code_hash, c.attempts
                                     FROM users u JOIN password_reset_codes c ON c.user_id = u.id
                                     WHERE u.email = ? AND c.used_at IS NULL AND c.expires_at > NOW()
                                     ORDER BY c.id DESC LIMIT 1 FOR UPDATE");
                $st->execute([$_SESSION['fp_email']]);
                $row = $st->fetch();

                if (!$row || (int)$row['attempts'] >= FP_MAX_TRIES) {
                    $error = 'This code is invalid or has expired. Please resend a new code.';
                } elseif (!password_verify($entered, $row['code_hash'])) {
                    $pdo->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?")
                        ->execute([$row['id']]);
                    $left  = max(0, FP_MAX_TRIES - 1 - (int)$row['attempts']);
                    $error = 'Invalid code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
                } else {
                    $_SESSION['fp_verified'] = ['code_id' => (int)$row['id'], 'user_id' => (int)$row['user_id']];
                }
                $pdo->commit();
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Something went wrong. Please try again.';
            }
            if (!$error) {
                header('Location: forgot-password.php');
                exit;
            }
        }
    }

    // ---- Step 3: new password (only after the code was verified) ----
    elseif ($action === 'password' && !empty($_SESSION['fp_verified'])) {
        $pass    = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if (strlen($pass) < 8)      { $error = 'Password must be at least 8 characters.'; }
        elseif ($pass !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            try {
                $pdo->beginTransaction();
                $v  = $_SESSION['fp_verified'];
                $st = $pdo->prepare("SELECT id FROM password_reset_codes
                                     WHERE id = ? AND user_id = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE");
                $st->execute([$v['code_id'], $v['user_id']]);

                if (!$st->fetch()) {
                    $pdo->rollBack();
                    fp_clear();
                    $error = 'Your session expired. Please start again.';
                } else {
                    $us = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                    $us->execute([$v['user_id']]);
                    $u = $us->fetch();
                    $pass_col = $u ? fp_pick_col($u, ['password', 'password_hash']) : null;

                    if (!$pass_col) {
                        $pdo->rollBack();
                        $error = 'Could not find the password column. Please contact support.';
                    } else {
                        $pdo->prepare("UPDATE users SET `$pass_col` = ? WHERE id = ?")
                            ->execute([password_hash($pass, PASSWORD_DEFAULT), $v['user_id']]);
                        $pdo->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?")
                            ->execute([$v['code_id']]);
                        $pdo->prepare("DELETE FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL")
                            ->execute([$v['user_id']]);
                        $pdo->commit();
                        fp_clear();
                        $done = true;
                    }
                }
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Could not change the password. Please try again.';
            }
        }
    }
}

$otp_failed = ($action === 'otp' && $error !== '');   // a wrong code (not a "please wait" message)
$info = $_SESSION['fp_info'] ?? '';
unset($_SESSION['fp_info']);

// Decide which step to show
if ($done)                                $step = 4;
elseif (!empty($_SESSION['fp_verified'])) $step = 3;
elseif (!empty($_SESSION['fp_email']))    $step = 2;
else                                      $step = 1;

// Seconds left before "Resend" works again
$resend_left = max(0, FP_RESEND_SECONDS - (time() - (int)($_SESSION['fp_sent_at'] ?? 0)));
$demo_code   = ($show_code && $step === 2) ? (string)($_SESSION['fp_dev_code'] ?? '') : '';

function fp_dots($n) {
    echo '<div class="dots">';
    for ($i = 1; $i <= 4; $i++) {
        $c = $i < $n ? 'done' : ($i === $n ? 'active' : '');
        echo "<div class=\"dot $c\"></div>";
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en" translate="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="google" content="notranslate">
<title>Forgot Password | Kabupaten Sukabumi</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --y:#f5b800;--y-dark:#8a6400;--y-deep:#c78a00;--y-soft:#fff3c4;--y-mid:#ffe08a;
  --text:#2b2410;--muted:#7a735f;--border:#efe3b5;--white:#fff;--ember:#ff9b42;--bad:#d64538;
}
body{
  font-family:'Poppins','Segoe UI',Arial,sans-serif;color:var(--text);
  background:linear-gradient(135deg,var(--y-soft),var(--y-mid),#ffd45c,var(--y-soft));
  background-size:300% 300%;animation:bgmove 14s ease infinite;
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;
}
@keyframes bgmove{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.card{
  width:100%;max-width:420px;background:var(--white);border-radius:28px;
  box-shadow:0 20px 60px rgba(138,100,0,.28);overflow:hidden;
  animation:cardUp .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.head{background:linear-gradient(135deg,var(--y-deep),var(--y));padding:2rem 2rem 3rem;position:relative}
.head::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:34px;background:var(--white);border-radius:34px 34px 0 0}
.back{display:inline-block;color:rgba(255,255,255,.9);font-size:.8rem;margin-bottom:.8rem;text-decoration:none}
.back:hover{color:#fff}
.brand{color:rgba(255,255,255,.9);font-size:.72rem;font-weight:600;letter-spacing:.06em;margin-bottom:4px}
.title{color:#fff;font-size:1.7rem;font-weight:700}
.dots{display:flex;align-items:center;position:absolute;top:1.2rem;right:1.5rem}
.dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.4);transition:all .3s}
.dot+.dot{margin-left:5px}
.dot.active{background:#fff;width:20px;border-radius:4px}
.dot.done{background:#fff3c4}
.body{padding:.5rem 2rem 2rem}
.icon-wrap{text-align:center;padding:1.2rem 0 .8rem}
.icon{
  width:85px;height:85px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;margin:0 auto .8rem;background:linear-gradient(135deg,#fffbe9,var(--y-soft));
  border:2px solid var(--y-mid);animation:bounce 2s ease-in-out infinite;
}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.sub{font-size:.8rem;color:var(--muted);line-height:1.5;margin-bottom:1.2rem;text-align:center}
.sub strong{color:var(--y-dark);word-break:break-all}
.err{background:#fdecea;color:#b71c1c;padding:.6rem .9rem;border-radius:10px;font-size:.8rem;margin-bottom:1rem;animation:shake .4s ease}
.okmsg{background:#e0f5e6;color:#1a7a35;padding:.6rem .9rem;border-radius:10px;font-size:.8rem;margin-bottom:1rem}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.in{display:flex;align-items:center;gap:8px;border:1.5px solid var(--border);border-radius:12px;padding:0 .9rem;background:#fffdf5;transition:all .2s}
.in:focus-within{border-color:var(--y);box-shadow:0 0 0 3px rgba(245,184,0,.2);background:#fff}
.in span{font-size:.95rem;color:#b9ad85}
.in input{flex:1;border:0;outline:0;padding:.75rem 0;font-size:.9rem;font-family:inherit;background:transparent;min-width:0}

/* ---- Step 2: ember code slots ---- */
.mail{
  display:flex;align-items:center;gap:12px;background:#2b2410;color:#fff;border-radius:16px;padding:.75rem .9rem;margin-bottom:1rem;
  box-shadow:0 10px 28px rgba(43,36,16,.35);animation:drop .55s cubic-bezier(.22,1,.36,1) both;
}
@keyframes drop{from{opacity:0;transform:translateY(-16px) scale(.97)}to{opacity:1;transform:none}}
.mail .mi{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#ffd45c,var(--ember));display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:none}
.mail .mt{flex:1;font-size:.74rem;line-height:1.45;min-width:0}
.mail .mt small{display:block;color:#c9bd94;font-size:.66rem;margin-bottom:1px}
.mail .mt b{font-size:.86rem;letter-spacing:.08em;color:#ffe9a8}
.mail .demo-tag{background:rgba(255,255,255,.14);border-radius:6px;padding:1px 6px;font-size:.6rem;margin-left:4px;vertical-align:1px}
.fillbtn{flex:none;background:#fff3c4;color:var(--text);border:0;border-radius:999px;padding:.45rem 1rem;font:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:transform .2s,background .2s}
.fillbtn:hover{background:#fff;transform:scale(1.05)}

.otp{display:flex;justify-content:center;gap:9px;margin:.2rem 0 .9rem}
.slot{position:relative}
.slot input{
  width:46px;height:58px;border:2px solid var(--border);border-radius:15px;background:#fffdf5;text-align:center;
  font-size:1.5rem;font-weight:700;font-family:inherit;color:var(--y-dark);outline:0;caret-color:transparent;
  transition:border-color .2s,box-shadow .25s,background .25s,transform .2s;
}
.slot::after{ /* the little wick: a dash at the bottom of an empty slot */
  content:'';position:absolute;left:50%;bottom:10px;width:14px;height:3px;margin-left:-7px;border-radius:3px;
  background:#ead9a0;transition:opacity .2s;pointer-events:none;
}
.slot.filled::after{opacity:0}
.slot input:focus{
  border-color:var(--ember);background:#fff;
  box-shadow:0 0 0 3px rgba(255,155,66,.22),0 0 20px rgba(255,155,66,.45);
}
.slot:focus-within::after{background:var(--ember);animation:flick 1s ease-in-out infinite}
@keyframes flick{0%,100%{opacity:1}50%{opacity:.35}}
.slot.filled input{background:linear-gradient(180deg,#fff7d6,#ffe9a8);border-color:var(--y)}
.slot.ignite input{animation:ignite .45s ease}
@keyframes ignite{
  0%{transform:scale(.88);box-shadow:0 0 0 rgba(255,155,66,0)}
  45%{transform:scale(1.1);box-shadow:0 0 26px rgba(255,155,66,.85)}
  100%{transform:scale(1)}
}
.otp.bad{animation:shake .45s ease}
.otp.bad .slot input{border-color:var(--bad);background:#fdecea;color:var(--bad);box-shadow:none}
.otp.bad .slot::after{background:#f2b3ad}
.otp-msg{min-height:1.3rem;text-align:center;font-size:.76rem;color:var(--bad);margin-bottom:.8rem}

.resend{display:flex;justify-content:center;margin-top:1rem}
.resend button{
  display:inline-flex;align-items:center;gap:8px;background:#fffdf5;border:1.5px solid var(--border);border-radius:999px;
  padding:.55rem 1.1rem;font:inherit;font-size:.78rem;color:var(--muted);cursor:not-allowed;transition:all .2s;
}
.resend button:not(:disabled){cursor:pointer;color:var(--y-dark);border-color:var(--y);font-weight:600}
.resend button:not(:disabled):hover{background:var(--y-soft)}
.spin{width:14px;height:14px;border-radius:50%;border:2.5px solid var(--y-mid);border-top-color:var(--y-deep);animation:rot .9s linear infinite;flex:none}
.resend button:not(:disabled) .spin{display:none}
@keyframes rot{to{transform:rotate(360deg)}}
.demo-note{text-align:center;font-size:.66rem;color:var(--muted);margin-top:.8rem}

.foot{text-align:center;font-size:.78rem;color:var(--muted);margin-top:1.2rem}
.foot a{color:var(--y-deep);font-weight:600;text-decoration:none}
.bar{height:3px;background:var(--border);border-radius:3px;margin-top:6px;overflow:hidden}
.fill{height:100%;width:0;border-radius:3px;transition:width .4s,background .4s}
.hint{font-size:.65rem;color:var(--muted);margin-top:3px}
.btn{
  display:block;width:100%;padding:.85rem;background:linear-gradient(135deg,var(--y),var(--y-deep));color:#2b2410;border:0;border-radius:14px;
  font-size:.95rem;font-weight:700;font-family:inherit;cursor:pointer;text-align:center;text-decoration:none;
  transition:transform .2s,box-shadow .2s,filter .2s;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(199,138,0,.4);filter:brightness(1.05)}
.btn:active{transform:scale(.98)}
.ok{text-align:center;padding:1.5rem 0}
.ok-circle{
  width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--y),#ffd45c);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.2rem;
  box-shadow:0 0 40px rgba(245,184,0,.5);animation:pop .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}70%{transform:scale(1.1)}to{transform:scale(1);opacity:1}}
.ok h3{font-size:1.3rem;color:var(--y-dark);margin-bottom:6px}
.ok p{font-size:.82rem;color:var(--muted);margin-bottom:1.5rem;line-height:1.5}
@media (max-width:380px){.slot input{width:40px;height:52px}.otp{gap:6px}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="card">

<?php if ($step === 1): ?>
  <div class="head">
    <?php fp_dots(1); ?>
    <a href="login.php" class="back">&larr; Back</a>
    <div class="brand">KABUPATEN SUKABUMI</div>
    <div class="title">Reset Password</div>
  </div>
  <div class="body">
    <div class="icon-wrap"><div class="icon">&#128272;</div></div>
    <div class="sub">Enter your email address to<br>receive a verification code</div>
    <?php if ($error): ?><div class="err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(fp_csrf()) ?>">
      <input type="hidden" name="action" value="email">
      <div class="field">
        <label for="email">Email Address</label>
        <div class="in"><span>&#9993;</span>
          <input id="email" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="Enter your email" autocomplete="email" required>
        </div>
      </div>
      <button class="btn" type="submit">Send Code &rarr;</button>
    </form>
    <div class="foot">Remember your password? <a href="login.php">Sign In</a></div>
  </div>

<?php elseif ($step === 2): ?>
  <div class="head">
    <?php fp_dots(2); ?>
    <a href="forgot-password.php?restart=1" class="back">&larr; Change email</a>
    <div class="brand">VERIFICATION</div>
    <div class="title">Enter your code</div>
  </div>
  <div class="body">

    <?php if ($demo_code !== ''): ?>
      <div class="mail" role="status">
        <div class="mi">&#9993;</div>
        <div class="mt">
          <small>Mail &middot; now<span class="demo-tag">DEMO</span></small>
          Your reset code is <b><?= e(substr($demo_code, 0, 3) . ' ' . substr($demo_code, 3)) ?></b>. It expires in <?= FP_CODE_MINUTES ?> minutes.
        </div>
        <button type="button" class="fillbtn" id="fill-btn" data-code="<?= e($demo_code) ?>">Fill</button>
      </div>
    <?php endif; ?>

    <div class="sub">If <strong><?= e($_SESSION['fp_email'] ?? '') ?></strong> is registered,<br>we emailed a <?= FP_CODE_LENGTH ?>-digit code to it.</div>
    <?php if ($info): ?><div class="okmsg" role="status"><?= e($info) ?></div><?php endif; ?>

    <form method="post" id="otp-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(fp_csrf()) ?>">
      <input type="hidden" name="action" value="otp">
      <div class="otp<?= $otp_failed ? ' bad' : '' ?>" id="otp-row" role="group" aria-label="<?= FP_CODE_LENGTH ?>-digit code">
        <?php for ($i = 0; $i < FP_CODE_LENGTH; $i++): ?>
          <div class="slot"><input type="text" name="otp[]" maxlength="1" inputmode="numeric" pattern="[0-9]"
               aria-label="Digit <?= $i + 1 ?>" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" required <?= $i === 0 ? 'autofocus' : '' ?>></div>
        <?php endfor; ?>
      </div>
      <div class="otp-msg" id="otp-msg" role="alert">
        <?php if ($otp_failed): ?>That code didn't work. <?= e($error) ?><?php elseif ($error): ?><?= e($error) ?><?php endif; ?>
      </div>
      <button class="btn" type="submit">Verify &rarr;</button>
    </form>

    <form method="post" class="resend">
      <input type="hidden" name="csrf" value="<?= e(fp_csrf()) ?>">
      <input type="hidden" name="action" value="resend">
      <button type="submit" id="resend-btn" <?= $resend_left > 0 ? 'disabled' : '' ?>>
        <span class="spin" aria-hidden="true"></span>
        <span id="resend-text"><?= $resend_left > 0 ? 'Resend code in 0:' . str_pad((string)$resend_left, 2, '0', STR_PAD_LEFT) : 'Resend code' ?></span>
      </button>
    </form>
    <?php if ($demo_code !== ''): ?>
      <div class="demo-note">Demo mode: no real email was sent.</div>
    <?php endif; ?>
  </div>

<?php elseif ($step === 3): ?>
  <div class="head">
    <?php fp_dots(3); ?>
    <div class="brand">ALMOST DONE</div>
    <div class="title">New Password</div>
  </div>
  <div class="body">
    <div class="icon-wrap"><div class="icon">&#128273;</div></div>
    <div class="sub">Your new password must be at least<br>8 characters long.</div>
    <?php if ($error): ?><div class="err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(fp_csrf()) ?>">
      <input type="hidden" name="action" value="password">
      <div class="field">
        <label for="pwd">New Password</label>
        <div class="in"><span>&#128274;</span>
          <input id="pwd" type="password" name="password" placeholder="Enter new password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="bar"><div class="fill" id="fill"></div></div>
        <div class="hint" id="hint">Enter a password</div>
      </div>
      <div class="field">
        <label for="cf">Confirm Password</label>
        <div class="in"><span>&#128274;</span>
          <input id="cf" type="password" name="confirm" placeholder="Confirm new password" minlength="8" autocomplete="new-password" required>
        </div>
      </div>
      <button class="btn" type="submit">Save &rarr;</button>
    </form>
  </div>

<?php else: ?>
  <div class="head" style="padding-bottom:2.5rem">
    <?php fp_dots(4); ?>
    <div class="brand">ALL DONE!</div>
    <div class="title">Password Changed</div>
  </div>
  <div class="body">
    <div class="ok">
      <div class="ok-circle">&#10003;</div>
      <h3>Password Changed!</h3>
      <p>Your password has been changed successfully.<br>You can now sign in.</p>
      <a href="login.php?reset=1" class="btn">Back to Sign In &rarr;</a>
    </div>
  </div>
<?php endif; ?>

</div>

<script>
(function () {
  /* ---------- Step 2: ember code slots ---------- */
  var form = document.getElementById('otp-form');
  if (form) {
    var row   = document.getElementById('otp-row');
    var msg   = document.getElementById('otp-msg');
    var boxes = [].slice.call(form.querySelectorAll('.otp input'));
    var sent  = false, timer = null;

    function mark() {
      boxes.forEach(function (b) { b.parentNode.classList.toggle('filled', b.value !== ''); });
    }
    function flare(b) {
      var s = b.parentNode;
      s.classList.remove('ignite'); void s.offsetWidth; s.classList.add('ignite');
    }
    function clearError() {
      if (row.classList.contains('bad')) { row.classList.remove('bad'); msg.textContent = ''; }
    }
    function allFilled() {
      return boxes.every(function (b) { return b.value !== ''; });
    }
    function submitSoon() {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (sent || !allFilled()) return;
        sent = true;
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
      }, 400);
    }
    function fill(code) {
      code = (code || '').replace(/\D/g, '').slice(0, boxes.length);
      if (!code) return;
      clearError();
      boxes.forEach(function (b, i) {
        b.value = code.charAt(i) || '';
        if (b.value) flare(b);
      });
      mark();
      var next = boxes.findIndex(function (b) { return b.value === ''; });
      (next < 0 ? boxes[boxes.length - 1] : boxes[next]).focus();
      if (code.length === boxes.length) submitSoon();
    }

    boxes.forEach(function (inp, i) {
      inp.addEventListener('input', function () {
        clearError();
        this.value = this.value.replace(/\D/g, '').slice(-1);
        if (this.value) {
          flare(this);
          if (i < boxes.length - 1) boxes[i + 1].focus();
        }
        mark();
        if (allFilled()) submitSoon();
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && i > 0) {
          boxes[i - 1].value = ''; mark(); boxes[i - 1].focus(); e.preventDefault();
        } else if (e.key === 'ArrowLeft' && i > 0)  { boxes[i - 1].focus(); e.preventDefault(); }
        else if (e.key === 'ArrowRight' && i < boxes.length - 1) { boxes[i + 1].focus(); e.preventDefault(); }
      });
      inp.addEventListener('focus', function () { this.select(); });
      inp.addEventListener('paste', function (e) {
        e.preventDefault();
        fill((e.clipboardData || window.clipboardData).getData('text'));
      });
    });
    mark();

    var fillBtn = document.getElementById('fill-btn');
    if (fillBtn) fillBtn.addEventListener('click', function () { fill(this.getAttribute('data-code')); });

    // Resend countdown
    var left = <?= (int)$resend_left ?>;
    var rb = document.getElementById('resend-btn'), rt = document.getElementById('resend-text');
    function fmt(n) { return '0:' + (n < 10 ? '0' : '') + n; }
    if (rb && left > 0) {
      var iv = setInterval(function () {
        left--;
        if (left <= 0) { clearInterval(iv); rb.disabled = false; rt.textContent = 'Resend code'; }
        else { rt.textContent = 'Resend code in ' + fmt(left); }
      }, 1000);
    }
  }

  /* ---------- Step 3: password strength ---------- */
  var pwd = document.getElementById('pwd');
  if (pwd) {
    pwd.addEventListener('input', function () {
      var v = this.value, s = 0;
      if (v.length >= 8) s++;
      if (v.length >= 12) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/[0-9]/.test(v)) s++;
      if (/[^A-Za-z0-9]/.test(v)) s++;
      var lv = [
        { w: '0%',   c: 'transparent', t: 'Enter a password' },
        { w: '25%',  c: '#ef4444',     t: 'Weak' },
        { w: '50%',  c: '#f97316',     t: 'Fair' },
        { w: '75%',  c: '#eab308',     t: 'Good' },
        { w: '100%', c: '#22c55e',     t: 'Strong' }
      ][Math.min(s, 4)];
      var f = document.getElementById('fill'), h = document.getElementById('hint');
      f.style.width = lv.w; f.style.background = lv.c;
      h.textContent = lv.t; h.style.color = lv.c === 'transparent' ? '' : lv.c;
    });
  }
})();
</script>
<script src="butterflies-swarm.js"></script> -->
</body>
</html>