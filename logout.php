<?php
/**
 * logout.php
 * Ends the session and shows a "bye bye" page with a waving mochi.
 */
require_once 'config.php';

// Visitors who are not logged in have nothing to log out from
if (!is_logged_in()) {
    redirect('index.php');
}

// Grab the first name before the session is destroyed
$user = current_user();
$first_name = $user ? explode(' ', $user['full_name'])[0] : '';

// ----- Destroy the session completely -----
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bye bye | <?= e(SITE_NAME) ?></title>
    <noscript><meta http-equiv="refresh" content="6;url=index.php"></noscript>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-card bye-card auth-animate">

        <div class="bye-hearts" aria-hidden="true">
            <span>💛</span><span>💛</span><span>💛</span>
        </div>

        <!-- Waving mochi mascot -->
        <div class="mascot" aria-hidden="true">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <!-- shadow -->
                <ellipse class="mascot-shadow" cx="100" cy="186" rx="52" ry="7" fill="rgba(0,0,0,0.12)"/>

                <!-- leaf on top -->
                <path d="M100 48 C96 30 110 20 122 24 C120 38 112 46 100 48 Z" fill="#7bc67e"/>

                <!-- body -->
                <path d="M40 122 C40 72 70 48 100 48 C130 48 160 72 160 122 C160 162 135 180 100 180 C65 180 40 162 40 122 Z"
                      fill="#fffdf5" stroke="#f0e2a8" stroke-width="3"/>

                <!-- left arm (resting) -->
                <ellipse cx="38" cy="132" rx="9" ry="16" transform="rotate(-18 38 132)"
                         fill="#fffdf5" stroke="#f0e2a8" stroke-width="3"/>

                <!-- right arm (waving) -->
                <g class="arm-wave">
                    <ellipse cx="170" cy="108" rx="9" ry="18" transform="rotate(22 170 108)"
                             fill="#fffdf5" stroke="#f0e2a8" stroke-width="3"/>
                </g>

                <!-- cheeks -->
                <circle cx="66" cy="128" r="9" fill="#ffb3b3" opacity="0.65"/>
                <circle cx="134" cy="128" r="9" fill="#ffb3b3" opacity="0.65"/>

                <!-- eyes -->
                <ellipse class="eye" cx="80" cy="110" rx="5" ry="7" fill="#3b2a14"/>
                <ellipse class="eye" cx="120" cy="110" rx="5" ry="7" fill="#3b2a14"/>

                <!-- smile -->
                <path d="M90 126 Q100 138 110 126" stroke="#3b2a14" stroke-width="3"
                      fill="none" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="bye-bubble">Bye bye<?= $first_name !== '' ? ', ' . e($first_name) : '' ?>! 👋</div>

        <h2>You have been logged out</h2>
        <p class="subtitle">See you again soon for more delicious Sukabumi culinary!</p>

        <div class="bye-progress"><span></span></div>
        <p class="text-muted" style="font-size:13px;">
            Going back to Home in <strong id="count">5</strong>s...
        </p>

        <div class="bye-actions">
            <a href="index.php" class="btn btn-outline btn-sm">Go Home</a>
            <a href="login.php" class="btn btn-primary btn-sm">Login Again</a>
        </div>

    </div>
</div>

<script>
// Countdown, then go back to the home page
(function () {
    var seconds = 5;
    var label = document.getElementById('count');

    var timer = setInterval(function () {
        seconds--;
        label.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = 'index.php';
        }
    }, 1000);
})();
</script>

<script src="butterflies.js"></script>

</body>
</html>