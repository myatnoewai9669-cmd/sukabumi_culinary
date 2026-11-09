<?php
/**
 * login.php
 * Lamp login page: pull the cord to turn on the light and show the form.
 */
require_once 'config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$old_email = '';
$flash = get_flash(); // e.g. "Registration successful!"

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $_SESSION['login_attempts']     = $_SESSION['login_attempts'] ?? 0;
    $_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $old_email = $email;

    if (time() < $_SESSION['login_locked_until']) {
        $wait  = $_SESSION['login_locked_until'] - time();
        $error = "Too many attempts. Please try again in {$wait} seconds.";
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            if ($row['status'] !== 'active') {
                $error = 'This account has been blocked.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['role']    = $row['role'];
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

                redirect($row['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php');
            }
        } else {
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 60;
                $_SESSION['login_attempts'] = 0;
            }
            $error = 'Incorrect email or password.';
        }
    }
}

// Start with the light ON when there is a message to show
$start_on = ($error !== '' || $flash !== null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body <?= $start_on ? 'lamp-on' : '' ?>" id="page">

<div class="lamp-scene">

    <!-- ========== LEFT: LAMP ========== -->
    <div class="lamp-side">
        <div class="lamp">

            <div class="light-cone"></div>

            <svg viewBox="0 0 200 420" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <ellipse class="shade-under" cx="100" cy="150" rx="70" ry="9"/>
                <path class="shade" d="M62 68 L138 68 L172 150 L28 150 Z"/>
                <ellipse class="shade-top" cx="100" cy="68" rx="38" ry="6"/>
                <circle class="bulb" cx="100" cy="158" r="14"/>
                <rect class="pole" x="96" y="170" width="8" height="195" rx="3"/>
                <rect class="pole-ring" x="92" y="250" width="16" height="6" rx="2"/>
                <ellipse class="base" cx="100" cy="372" rx="52" ry="11"/>
            </svg>

            <!-- Pull cord -->
            <button type="button" class="cord" id="cord" aria-label="Pull the cord to turn the light on or off"></button>
        </div>

        <p class="lamp-hint" id="hint">Pull the cord to turn on the light</p>
    </div>

    <!-- ========== RIGHT: LOGIN FORM ========== -->
    <div class="login-side">
        <div class="auth-card login-card" id="loginCard">

            <a href="index.php" class="brand">
                <?php if (file_exists(__DIR__ . '/logo.png')): ?>
                    <img src="logo.png" alt="Kabupaten Sukabumi logo">
                <?php else: ?>
                    <span class="logo-fallback">KS</span>
                <?php endif; ?>
                <span>Kabupaten<br>Sukabumi</span>
            </a>

            <h2>Welcome back.</h2>
            <p class="subtitle">Pull up a chair and taste Sukabumi.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($old_email) ?>" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Your password" required>
                        <button type="button" class="toggle-pass" data-target="password" aria-label="Show password">👁</button>
                    </div>
                </div>

                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>

                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </form>

            <p class="auth-links">
                No account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>

</div>

<script>
(function () {
    var page = document.getElementById('page');
    var cord = document.getElementById('cord');
    var hint = document.getElementById('hint');
    var email = document.getElementById('email');

    function updateHint() {
        hint.textContent = page.classList.contains('lamp-on')
            ? 'Pull again for dark'
            : 'Pull the cord to turn on the light';
    }
    updateHint();

    // Pull the cord: animate, then toggle the light
    cord.addEventListener('click', function () {
        cord.classList.remove('pulled');
        void cord.offsetWidth; // restart the animation
        cord.classList.add('pulled');

        setTimeout(function () {
            page.classList.toggle('lamp-on');
            updateHint();
            if (page.classList.contains('lamp-on')) {
                setTimeout(function () { email.focus(); }, 700);
            }
        }, 180);
    });

    // Show / hide password: 👁 = hidden, 🙈 = visible
    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? '🙈' : '👁';
        });
    });
})();
</script>

<script src="butterflies.js"></script>

</body>
</html>