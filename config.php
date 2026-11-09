<?php
/**
 * config.php
 * Database connection, session setup and shared helper functions
 * for the Kabupaten Sukabumi Culinary Platform.
 */

// ---------- Database settings ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'sukabumi_culinary');
define('DB_USER', 'root');   // change on your hosting
define('DB_PASS', '');       // change on your hosting
define('DB_CHARSET', 'utf8mb4');

// ---------- Site settings ----------
define('SITE_NAME', 'Kabupaten Sukabumi');
define('SHIPPING_FEE', 10000);
// ---------- Gemini AI settings ----------
define('GEMINI_API_KEY', 'YOUR OWN API KEY');
define('GEMINI_MODEL', 'XXXXXX');

// ---------- Error reporting ----------
// Set to false on a live server
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// ---------- PDO connection ----------
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit(DEBUG_MODE ? 'Database error: ' . $e->getMessage() : 'Service temporarily unavailable.');
}

// ---------- Helper functions ----------

// Escape output to prevent XSS
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Redirect and stop the script
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Format number as Indonesian Rupiah, e.g. Rp 15.000
function rupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

// ---------- Flash messages ----------
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ---------- CSRF protection ----------
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form. Please go back and try again.');
    }
}

// ---------- Authentication helpers ----------
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Current user row, or null if not logged in
function current_user() {
    global $pdo;
    if (!is_logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, points, language FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// Block guests from a page
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in first.');
        redirect('login.php');
    }
}

// Block non-admins from a page
function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}
