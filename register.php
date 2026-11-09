<?php
/**
 * register.php
 * New user registration page.
 */
require_once 'config.php';

// Already logged in? Go home.
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old = ['full_name' => $full_name, 'email' => $email, 'phone' => $phone];

    // ----- Validation -----
    if (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 100) {
        $errors['full_name'] = 'Full name must be 3 to 100 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain letters and numbers.';
    }

    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // ----- Check if the email already exists -----
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    // ----- Save the new user -----
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$full_name, $email, $phone !== '' ? $phone : null, $hash]);

        set_flash('success', 'Registration successful! Please log in.');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-card auth-animate">

        <a href="index.php" class="brand">
            <?php if (file_exists(__DIR__ . '/logo.png')): ?>
                <img src="logo.png" alt="Kabupaten Sukabumi logo">
            <?php else: ?>
                <span class="logo-fallback">KS</span>
            <?php endif; ?>
            <span>Kabupaten<br>Sukabumi</span>
        </a>

        <h2>Create Account</h2>
        <p class="subtitle">Join the Sukabumi Culinary Platform</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">Please fix the errors below.</div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= e($old['full_name']) ?>" placeholder="Your full name" required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="form-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($old['email']) ?>" placeholder="you@example.com" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone">Phone (optional)</label>
                <input type="text" id="phone" name="phone" class="form-control"
                       value="<?= e($old['phone']) ?>" placeholder="08xxxxxxxxxx">
                <?php if (isset($errors['phone'])): ?>
                    <div class="form-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="At least 8 characters" required>
                    <button type="button" class="toggle-pass" data-target="password" aria-label="Show password">👁</button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Repeat your password" required>
                    <button type="button" class="toggle-pass" data-target="confirm_password" aria-label="Show password">👁</button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="form-error"><?= e($errors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <p class="auth-links">
            Already have an account? <a href="login.php">Login</a>
        </p>

    </div>
</div>

<script>
// Show / hide password: 👁 = hidden, 🙈 = visible
document.querySelectorAll('.toggle-pass').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? '🙈' : '👁';
    });
});
</script>

<script src="butterflies.js"></script>

</body>
</html>