<?php
/**
 * ai-chat.php
 * Chat endpoint: uses Gemini when a key is set, otherwise recommends foods from the database.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (!defined('GEMINI_API_KEY')) { define('GEMINI_API_KEY', 'XXXXXXX YOUR API'); }
if (!defined('GEMINI_MODEL'))   { define('GEMINI_MODEL', 'XXXXX'); }

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ---------- Security: session token ----------
$token = (string)($input['token'] ?? '');
if (empty($_SESSION['ai_token']) || !hash_equals($_SESSION['ai_token'], $token)) {
    out(['error' => 'Session expired. Please refresh the page.'], 403);
}

// ---------- Reset chat ----------
if (!empty($input['reset'])) {
    $_SESSION['ai_history'] = [];
    out(['ok' => true]);
}

// ---------- Validate message ----------
$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    out(['error' => 'Please type a message.'], 400);
}
if (mb_strlen($message) > 300) {
    out(['error' => 'Your message is too long (maximum 300 characters).'], 400);
}

// ---------- Rate limit: 20 messages per 10 minutes ----------
$now = time();
$hits = array_values(array_filter($_SESSION['ai_hits'] ?? [], function ($t) use ($now) {
    return $t > $now - 600;
}));
if (count($hits) >= 20) {
    out(['error' => 'Too many messages. Please wait a few minutes.'], 429);
}
$hits[] = $now;
$_SESSION['ai_hits'] = $hits;

// ---------- Load foods ----------
$foods = $pdo->query(
    "SELECT * FROM foods WHERE is_active = 1 ORDER BY is_popular DESC, id ASC LIMIT 60"
)->fetchAll();

// ---------- Local mode: simple keyword matching ----------
function local_answer($message, $foods) {
    $hints = [
        'sweet'       => ['sweet', 'manis', 'mochi', 'colenak', 'peanut', 'sugar', 'dessert', 'snack'],
        'manis'       => ['sweet', 'manis', 'mochi', 'colenak', 'peanut', 'sugar', 'dessert', 'snack'],
        'spicy'       => ['spicy', 'pedas', 'sambal', 'chili', 'cabai'],
        'pedas'       => ['spicy', 'pedas', 'sambal', 'chili', 'cabai'],
        'warm'        => ['warm', 'hangat', 'soup', 'sup', 'liwet', 'rice', 'nasi'],
        'hangat'      => ['warm', 'hangat', 'soup', 'sup', 'liwet', 'rice', 'nasi'],
        'soup'        => ['soup', 'sup', 'warm', 'hangat'],
        'traditional' => ['traditional', 'tradisional', 'nasi', 'liwet', 'sukabumi'],
        'tradisional' => ['traditional', 'tradisional', 'nasi', 'liwet', 'sukabumi'],
        'snack'       => ['snack', 'camilan', 'mochi', 'colenak', 'bandros'],
        'drink'       => ['drink', 'minum', 'tea', 'teh', 'coffee', 'kopi'],
    ];

    $text   = mb_strtolower($message);
    $terms  = [];
    foreach (preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (mb_strlen($w) >= 3) {
            $terms[] = $w;
            if (isset($hints[$w])) {
                $terms = array_merge($terms, $hints[$w]);
            }
        }
    }

    $scored = [];
    foreach ($foods as $f) {
        $hay   = mb_strtolower($f['name'] . ' ' . ($f['description'] ?? '') . ' ' . ($f['ingredients'] ?? ''));
        $score = 0;
        foreach ($terms as $t) {
            if (mb_strpos($hay, $t) !== false) { $score++; }
        }
        if ($score > 0) {
            $scored[] = ['score' => $score, 'food' => $f];
        }
    }
    usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });

    $picked = array_slice(array_column($scored, 'food'), 0, 2);
    if (empty($picked)) {
        $picked = array_slice($foods, 0, 2);
        $reply  = "I could not find an exact match, but these popular foods from Sukabumi are worth a try:";
    } else {
        $reply = "Here is what I suggest from Sukabumi for you:";
    }
    return ['reply' => $reply, 'foods' => $picked];
}

// ---------- Gemini mode ----------
function gemini_answer($message, $foods, $history) {
    $list = [];
    foreach ($foods as $f) {
        $list[] = $f['slug'] . ' | ' . $f['name'] . ' | ' . mb_substr((string)($f['description'] ?? ''), 0, 120);
    }

    $system = "You are a friendly recipe and food assistant for the Sukabumi Culinary website (West Java, Indonesia). "
        . "Answer in the same language the user writes (English, Indonesian or Sundanese). Keep every answer under 60 words. "
        . "When you recommend foods, choose only from this list (slug | name | description):\n" . implode("\n", $list)
        . "\nReturn JSON only in this shape: {\"reply\": \"text\", \"slugs\": [\"up to 2 slugs from the list, or an empty array\"]}.";

    $contents = [];
    foreach ($history as $h) {
        $contents[] = ['role' => $h['role'] === 'user' ? 'user' : 'model', 'parts' => [['text' => $h['text']]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $payload = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents'           => $contents,
        'generationConfig'   => [
            'temperature'      => 0.7,
            'maxOutputTokens'  => 400,
            'responseMimeType' => 'application/json',
        ],
    ];

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $http !== 200) {
        return null;   // fall back to local mode
    }

    $data = json_decode($raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $json = json_decode($text, true);
    if (!is_array($json) || empty($json['reply'])) {
        return null;
    }

    $by_slug = [];
    foreach ($foods as $f) { $by_slug[$f['slug']] = $f; }

    $picked = [];
    foreach ((array)($json['slugs'] ?? []) as $slug) {
        if (isset($by_slug[$slug]) && count($picked) < 2) {
            $picked[] = $by_slug[$slug];
        }
    }
    return ['reply' => (string)$json['reply'], 'foods' => $picked];
}

// ---------- Answer ----------
$history = $_SESSION['ai_history'] ?? [];
$result  = null;
$mode    = 'local';

if (GEMINI_API_KEY !== '') {
    $result = gemini_answer($message, $foods, $history);
    if ($result !== null) { $mode = 'gemini'; }
}
if ($result === null) {
    $result = local_answer($message, $foods);
}

// Keep the last 8 messages for context
$history[] = ['role' => 'user',  'text' => $message];
$history[] = ['role' => 'model', 'text' => $result['reply']];
$_SESSION['ai_history'] = array_slice($history, -8);

$cards = [];
foreach ($result['foods'] as $f) {
    $cards[] = [
        'name'  => $f['name'],
        'slug'  => $f['slug'],
        'text'  => mb_substr((string)($f['description'] ?? ''), 0, 90),
        'image' => !empty($f['main_image']) ? 'uploads/' . $f['main_image'] : '',
        'price' => rupiah($f['price']),
    ];
}

out(['reply' => $result['reply'], 'foods' => $cards, 'mode' => $mode]);
