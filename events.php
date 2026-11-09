<?php
/**
 * events.php
 * Events: featured banner, month calendar, upcoming list, join button.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Change this if your login saves the user id under another session key
$user_id = (int)($_SESSION['user_id'] ?? 0);

// ---------- Join an event (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join') {
    if ($user_id === 0) {
        header('Location: login.php');
        exit;
    }
    $event_id = (int)($_POST['event_id'] ?? 0);
    try {
        $ins = $pdo->prepare("INSERT IGNORE INTO event_registrations (event_id, user_id) VALUES (:e, :u)");
        $ins->execute([':e' => $event_id, ':u' => $user_id]);
        $_SESSION['flash'] = 'You joined this event. See you there!';
    } catch (Exception $ex) {
        $_SESSION['flash'] = 'Could not join this event. Please try again.';
    }
    header('Location: events.php');
    exit;
}

$page_title  = 'Events';
$active_page = 'events';
require_once 'header.php';

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ---------- Helpers ----------
function event_date_range($start, $end) {
    $s = strtotime($start);
    $e = strtotime($end);
    if ($start === $end) {
        return date('j M Y', $s);
    }
    if (date('Y-m', $s) === date('Y-m', $e)) {
        return date('j', $s) . ' - ' . date('j M Y', $e);
    }
    return date('j M', $s) . ' - ' . date('j M Y', $e);
}

// ---------- Joined events of this user ----------
$joined = [];
if ($user_id > 0) {
    $js = $pdo->prepare("SELECT event_id FROM event_registrations WHERE user_id = :u");
    $js->execute([':u' => $user_id]);
    $joined = array_map('intval', $js->fetchAll(PDO::FETCH_COLUMN));
}

// ---------- Featured event (next upcoming featured) ----------
$feat = $pdo->query(
    "SELECT * FROM events
     WHERE is_active = 1 AND end_date >= CURDATE()
     ORDER BY is_featured DESC, start_date ASC
     LIMIT 1"
)->fetch();

// ---------- Upcoming events list ----------
$upcoming = $pdo->query(
    "SELECT * FROM events
     WHERE is_active = 1 AND end_date >= CURDATE()
     ORDER BY start_date ASC"
)->fetchAll();

// ---------- Calendar month ----------
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}
$first_ts   = strtotime($month . '-01');
$days_in    = (int)date('t', $first_ts);
$start_wday = (int)date('w', $first_ts);   // 0 = Sunday
$prev_month = date('Y-m', strtotime('-1 month', $first_ts));
$next_month = date('Y-m', strtotime('+1 month', $first_ts));

$month_start = $month . '-01';
$month_end   = $month . '-' . $days_in;
$cal_stmt = $pdo->prepare(
    "SELECT title, start_date, end_date FROM events
     WHERE is_active = 1 AND start_date <= :me AND end_date >= :ms"
);
$cal_stmt->execute([':ms' => $month_start, ':me' => $month_end]);

$event_days = [];   // day number => event title
foreach ($cal_stmt->fetchAll() as $ev) {
    for ($d = 1; $d <= $days_in; $d++) {
        $date = sprintf('%s-%02d', $month, $d);
        if ($date >= $ev['start_date'] && $date <= $ev['end_date']) {
            $event_days[$d] = $ev['title'];
        }
    }
}
$today = date('Y-m-d');
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0; color: #6b6b6b; }

    .flash { background: #fff8dc; border: 1px solid #f0e2a8; padding: 12px 16px; border-radius: 12px; margin: 18px 0 0; }

    .feature {
        position: relative; border-radius: 22px; overflow: hidden; color: #fff; min-height: 300px;
        background: linear-gradient(135deg, #3b2a00, #b8860b); display: flex; align-items: flex-end;
        box-shadow: 0 14px 36px rgba(0,0,0,.18);
    }
    .feature img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .55; }
    .feature .inner { position: relative; padding: 28px; width: 100%; background: linear-gradient(transparent, rgba(0,0,0,.65)); }
    .feature h2 { margin: 0 0 6px; font-size: 34px; }
    .feature .meta { display: flex; gap: 18px; flex-wrap: wrap; margin: 10px 0 14px; opacity: .95; }

    .events-layout { display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; margin-top: 30px; align-items: start; }
    @media (max-width: 850px) { .events-layout { grid-template-columns: 1fr; } }

    .event-card {
        display: grid; grid-template-columns: 74px 1fr auto; gap: 16px; align-items: center;
        background: #fff; border-radius: 16px; padding: 14px; margin-bottom: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,.05); transition: transform .25s;
    }
    .event-card:hover { transform: translateY(-3px); }
    @media (max-width: 600px) { .event-card { grid-template-columns: 64px 1fr; } .event-card form, .event-card .joined { grid-column: 1 / -1; } }
    .date-box { background: #fff3c4; border-radius: 14px; text-align: center; padding: 10px 0; }
    .date-box .day { font-size: 26px; font-weight: 700; line-height: 1; }
    .date-box .mon { font-size: 12px; text-transform: uppercase; color: #8a6400; }
    .event-card h3 { margin: 0 0 4px; font-size: 17px; }
    .event-card p { margin: 0; color: #777; font-size: 13px; }
    .joined { color: #2a9d4b; font-weight: 600; }

    .calendar { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 8px 26px rgba(0,0,0,.07); }
    .cal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .cal-head a { text-decoration: none; padding: 4px 12px; border-radius: 10px; background: #fff8dc; color: #444; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; text-align: center; }
    .cal-grid .dow { font-size: 12px; color: #999; padding-bottom: 4px; }
    .cal-grid .day { padding: 9px 0; border-radius: 10px; font-size: 14px; }
    .cal-grid .has-event { background: var(--yellow, #f5b800); font-weight: 700; cursor: help; }
    .cal-grid .is-today { outline: 2px solid #222; }

    .empty-state { text-align: center; padding: 40px 10px; color: #888; }
</style>

<section class="page-hero">
    <div class="container">
        <h1 class="reveal">Culinary <span class="text-yellow">Events</span> &amp; Festivals</h1>
        <p class="reveal delay-1">Join food festivals, workshops and fairs around Sukabumi.</p>
        <?php if ($flash !== ''): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= FEATURED EVENT ================= -->
        <?php if ($feat): ?>
            <div class="feature reveal">
                <?php if (!empty($feat['image'])): ?>
                    <img src="uploads/<?= e($feat['image']) ?>" alt="<?= e($feat['title']) ?>"
                         onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="inner">
                    <h2><?= e($feat['title']) ?></h2>
                    <div><?= e($feat['description']) ?></div>
                    <div class="meta">
                        <span>📅 <?= e(event_date_range($feat['start_date'], $feat['end_date'])) ?></span>
                        <span>📍 <?= e($feat['location']) ?></span>
                    </div>

                    <?php if (in_array((int)$feat['id'], $joined, true)): ?>
                        <span class="joined" style="background:#fff; padding:8px 16px; border-radius:12px;">✓ You joined</span>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="join">
                            <input type="hidden" name="event_id" value="<?= (int)$feat['id'] ?>">
                            <button type="submit" class="btn btn-primary">Join Now &rarr;</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="events-layout">

            <!-- ================= UPCOMING LIST ================= -->
            <div>
                <h2 class="reveal">Upcoming Events</h2>

                <?php if (empty($upcoming)): ?>
                    <div class="empty-state">
                        <div style="font-size:48px;">🎪</div>
                        <p>No upcoming events yet. Check back soon!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $i => $ev): ?>
                        <div class="event-card reveal" style="transition-delay: <?= ($i % 4) * 0.08 ?>s">
                            <div class="date-box">
                                <div class="day"><?= e(date('j', strtotime($ev['start_date']))) ?></div>
                                <div class="mon"><?= e(date('M', strtotime($ev['start_date']))) ?></div>
                            </div>

                            <div>
                                <h3><?= e($ev['title']) ?></h3>
                                <p>📅 <?= e(event_date_range($ev['start_date'], $ev['end_date'])) ?></p>
                                <p>📍 <?= e($ev['location']) ?></p>
                            </div>

                            <?php if (in_array((int)$ev['id'], $joined, true)): ?>
                                <span class="joined">✓ Joined</span>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="join">
                                    <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                    <button type="submit" class="btn btn-primary">Join</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ================= CALENDAR ================= -->
            <aside class="calendar reveal">
                <div class="cal-head">
                    <a href="events.php?month=<?= e($prev_month) ?>" aria-label="Previous month">&lsaquo;</a>
                    <strong><?= e(date('F Y', $first_ts)) ?></strong>
                    <a href="events.php?month=<?= e($next_month) ?>" aria-label="Next month">&rsaquo;</a>
                </div>

                <div class="cal-grid">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
                        <div class="dow"><?= $dow ?></div>
                    <?php endforeach; ?>

                    <?php for ($b = 0; $b < $start_wday; $b++): ?><div></div><?php endfor; ?>

                    <?php for ($d = 1; $d <= $days_in; $d++):
                        $date_str = sprintf('%s-%02d', $month, $d);
                        $classes  = 'day';
                        if (isset($event_days[$d])) { $classes .= ' has-event'; }
                        if ($date_str === $today)   { $classes .= ' is-today'; }
                    ?>
                        <div class="<?= $classes ?>" <?= isset($event_days[$d]) ? 'title="' . e($event_days[$d]) . '"' : '' ?>>
                            <?= $d ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </aside>

        </div>
    </div>
</section>

<script>
// Scroll reveal animation
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.reveal');

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    items.forEach(function (el) { observer.observe(el); });
});
</script>

<?php require_once 'footer.php'; ?>