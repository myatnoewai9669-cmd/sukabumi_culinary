<?php
/**
 * rewards.php
 * Foodie Rewards: points balance, progress bar, redeem vouchers, history.
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tries a few common session keys for the logged-in user id
$user_id = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));

// ---------- Redeem a reward (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem') {
    if ($user_id === 0) {
        header('Location: login.php');
        exit;
    }

    $reward_id = (int)($_POST['reward_id'] ?? 0);
    $rs = $pdo->prepare("SELECT * FROM reward_catalog WHERE id = :id AND is_active = 1");
    $rs->execute([':id' => $reward_id]);
    $reward = $rs->fetch();

    if (!$reward) {
        $_SESSION['flash'] = 'This reward is not available.';
    } else {
        try {
            $pdo->beginTransaction();

            // Subtract points only if the user has enough
            $upd = $pdo->prepare("UPDATE users SET points = points - :cost WHERE id = :uid AND points >= :need");
            $upd->execute([
                ':cost' => $reward['points_cost'],
                ':need' => $reward['points_cost'],
                ':uid'  => $user_id,
            ]);

            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                $_SESSION['flash'] = 'Not enough points for this reward.';
            } else {
                $code = 'VCH-' . strtoupper(bin2hex(random_bytes(3)));

                $pdo->prepare(
                    "INSERT INTO reward_redemptions (user_id, reward_id, code, points_spent)
                     VALUES (:u, :r, :c, :p)"
                )->execute([
                    ':u' => $user_id, ':r' => $reward['id'],
                    ':c' => $code,    ':p' => $reward['points_cost'],
                ]);

                $pdo->prepare(
                    "INSERT INTO point_history (user_id, points, reason) VALUES (:u, :p, :why)"
                )->execute([
                    ':u'   => $user_id,
                    ':p'   => -((int)$reward['points_cost']),
                    ':why' => 'Redeemed: ' . $reward['title'],
                ]);

                $pdo->commit();
                $_SESSION['flash'] = 'Reward redeemed! Your voucher code is ' . $code;
            }
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash'] = 'Could not redeem this reward. Please try again.';
        }
    }

    header('Location: rewards.php');
    exit;
}

$page_title  = 'Foodie Rewards';
$active_page = 'rewards';
require_once 'header.php';

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ---------- Load data ----------
$points = 0;
if ($user_id > 0) {
    $ps = $pdo->prepare("SELECT points FROM users WHERE id = :id");
    $ps->execute([':id' => $user_id]);
    $points = (int)$ps->fetchColumn();
}

$rewards = $pdo->query(
    "SELECT * FROM reward_catalog WHERE is_active = 1 ORDER BY points_cost ASC"
)->fetchAll();

// Next reward the user can reach
$next_cost = 0;
foreach ($rewards as $rw) {
    if ((int)$rw['points_cost'] > $points) {
        $next_cost = (int)$rw['points_cost'];
        break;
    }
}
$all_reached = (!empty($rewards) && $next_cost === 0);
if ($all_reached) {
    $next_cost = (int)end($rewards)['points_cost'];
}
$progress = $next_cost > 0 ? min(100, (int)round($points / $next_cost * 100)) : 0;

$vouchers = [];
$history  = [];
if ($user_id > 0) {
    $vs = $pdo->prepare(
        "SELECT rr.code, rr.created_at, rc.title
         FROM reward_redemptions rr
         JOIN reward_catalog rc ON rc.id = rr.reward_id
         WHERE rr.user_id = :u ORDER BY rr.id DESC LIMIT 10"
    );
    $vs->execute([':u' => $user_id]);
    $vouchers = $vs->fetchAll();

    $hs = $pdo->prepare(
        "SELECT points, reason, created_at FROM point_history
         WHERE user_id = :u ORDER BY id DESC LIMIT 10"
    );
    $hs->execute([':u' => $user_id]);
    $history = $hs->fetchAll();
}
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0; color: #6b6b6b; }

    .flash { background: #fff8dc; border: 1px solid #f0e2a8; padding: 12px 16px; border-radius: 12px; margin: 18px 0 0; animation: fadeIn .4s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

    .points-card {
        background: linear-gradient(135deg, #fff3c4, #ffe08a); border-radius: 24px; padding: 30px;
        text-align: center; box-shadow: 0 12px 34px rgba(184,134,11,.2); position: relative; overflow: hidden;
    }
    .points-card .crown { font-size: 52px; animation: floaty 2.4s ease-in-out infinite; display: inline-block; }
    @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    .points-card .label { color: #7a5b00; margin-top: 4px; }
    .points-card .big { font-size: 44px; font-weight: 800; margin: 4px 0 14px; }

    .bar { height: 14px; background: rgba(255,255,255,.7); border-radius: 999px; overflow: hidden; max-width: 460px; margin: 0 auto; }
    .bar span { display: block; height: 100%; background: linear-gradient(90deg, #f5b800, #e09a00); border-radius: 999px; animation: grow 1.2s ease-out; }
    @keyframes grow { from { width: 0; } }
    .next-note { margin-top: 10px; color: #7a5b00; font-size: 14px; }

    .rewards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-top: 16px; }
    .reward-card {
        background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 6px 20px rgba(0,0,0,.06);
        display: flex; flex-direction: column; gap: 8px; transition: transform .25s;
    }
    .reward-card:hover { transform: translateY(-4px); }
    .reward-card .icon { font-size: 32px; }
    .reward-card h3 { margin: 0; font-size: 17px; }
    .reward-card p { margin: 0; color: #777; font-size: 13px; flex: 1; }
    .reward-card .cost { font-weight: 700; color: #b07a00; }
    .reward-card button[disabled] { opacity: .45; cursor: not-allowed; }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 36px; }
    @media (max-width: 800px) { .two-col { grid-template-columns: 1fr; } }
    .panel { background: #fff; border-radius: 18px; padding: 20px; box-shadow: 0 6px 20px rgba(0,0,0,.06); }
    .panel h3 { margin: 0 0 12px; }
    .row { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f4ecc8; font-size: 14px; }
    .row:last-child { border-bottom: 0; }
    .code { font-family: monospace; background: #fff3c4; padding: 2px 8px; border-radius: 8px; font-weight: 700; }
    .plus { color: #2a9d4b; font-weight: 700; }
    .minus { color: #d33; font-weight: 700; }
    .muted { color: #999; }
</style>

<section class="page-hero">
    <div class="container">
        <h1 class="reveal">Sukabumi <span class="text-yellow">Foodie</span> Rewards</h1>
        <p class="reveal delay-1">Earn points with every order and redeem them for vouchers.</p>
        <?php if ($flash !== ''): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= POINTS CARD ================= -->
        <div class="points-card reveal">
            <span class="crown">👑</span>
            <div class="label">Your Points</div>

            <?php if ($user_id === 0): ?>
                <div class="big">— Pts</div>
                <p>Log in to see your points and redeem rewards.</p>
                <a href="login.php" class="btn btn-primary">Log in</a>
            <?php else: ?>
                <div class="big"><?= number_format($points) ?> Pts</div>
                <?php if (!empty($rewards)): ?>
                    <div class="bar"><span style="width: <?= $progress ?>%"></span></div>
                    <div class="next-note">
                        <?php if ($all_reached): ?>
                            You can redeem every reward. 🎉
                        <?php else: ?>
                            Next Reward: <?= number_format($next_cost) ?> Pts
                            (<?= number_format($next_cost - $points) ?> more to go)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <p style="margin-top:14px;">
                    <a href="#available" class="btn btn-primary">View Rewards &rarr;</a>
                </p>
            <?php endif; ?>
        </div>

        <!-- ================= AVAILABLE REWARDS ================= -->
        <h2 id="available" class="reveal" style="margin-top:40px;">Available Rewards</h2>

        <?php if (empty($rewards)): ?>
            <p class="muted">No rewards available yet.</p>
        <?php else: ?>
            <div class="rewards-grid">
                <?php foreach ($rewards as $i => $rw):
                    $can = ($user_id > 0 && $points >= (int)$rw['points_cost']);
                ?>
                    <div class="reward-card reveal" style="transition-delay: <?= ($i % 4) * 0.08 ?>s">
                        <div class="icon">🎟️</div>
                        <h3><?= e($rw['title']) ?></h3>
                        <p><?= e($rw['description']) ?></p>
                        <div class="cost"><?= number_format((int)$rw['points_cost']) ?> Pts</div>

                        <form method="post" onsubmit="return confirm('Redeem this reward?');">
                            <input type="hidden" name="action" value="redeem">
                            <input type="hidden" name="reward_id" value="<?= (int)$rw['id'] ?>">
                            <button type="submit" class="btn btn-primary" <?= $can ? '' : 'disabled' ?>>Redeem</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ================= VOUCHERS + HISTORY ================= -->
        <?php if ($user_id > 0): ?>
            <div class="two-col">
                <div class="panel reveal">
                    <h3>🎁 My Vouchers</h3>
                    <?php if (empty($vouchers)): ?>
                        <p class="muted">You have not redeemed any reward yet.</p>
                    <?php else: ?>
                        <?php foreach ($vouchers as $v): ?>
                            <div class="row">
                                <span><?= e($v['title']) ?></span>
                                <span class="code"><?= e($v['code']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="panel reveal delay-1">
                    <h3>📜 Points History</h3>
                    <?php if (empty($history)): ?>
                        <p class="muted">No points activity yet.</p>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <div class="row">
                                <span><?= e($h['reason']) ?><br><small class="muted"><?= e(date('j M Y', strtotime($h['created_at']))) ?></small></span>
                                <span class="<?= $h['points'] >= 0 ? 'plus' : 'minus' ?>">
                                    <?= $h['points'] >= 0 ? '+' : '' ?><?= (int)$h['points'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

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