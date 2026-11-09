<?php
session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Help topics and FAQs (edit freely, or load them from your database) ----
$topics = [
    'orders'  => ['Orders & delivery', 'M6 2l-2 4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4H6zm0 4h12M9 10a3 3 0 0 0 6 0'],
    'payment' => ['Payment',           'M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 10h18'],
    'account' => ['Account',           'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z'],
    'food'    => ['Food & halal',      'M7 2v9M4 2v5a3 3 0 0 0 6 0V2M7 11v11M17 2c-2 2-3 5-3 8h3v12'],
    'rewards' => ['Rewards',           'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4'],
];

$faqs = [
    ['orders',  'How do I place an order?',
        'Open a food page, choose the quantity and tap Add to Cart. Then open your cart, tap Proceed to Checkout, fill in your delivery details and place the order.'],
    ['orders',  'How long does delivery take?',
        'Regular delivery takes 2 to 3 days and express delivery takes 1 to 2 days. The exact time is shown at checkout before you pay.'],
    ['orders',  'Where can I see my order status?',
        'Go to Profile, then My Orders. Each order shows its status: Processing, Shipped or Delivered.'],
    ['orders',  'Can I cancel an order?',
        'You can cancel while the order is still Processing. After it has shipped, please contact us and we will help you.'],
    ['payment', 'Which payment methods can I use?',
        'We accept bank transfer, cash on delivery, and e-wallets such as OVO, GoPay and Dana.'],
    ['payment', 'How do I use a coupon code?',
        'Enter the code in the coupon box on your cart page and tap Apply. The discount appears in your total right away.'],
    ['payment', 'Is my payment secure?',
        'Yes. Payments go through trusted payment partners, and we never store your card or wallet password.'],
    ['account', 'How do I change my profile photo?',
        'Open Profile, choose a JPG, PNG or WEBP image up to 2 MB under Profile Photo, then tap Save Changes.'],
    ['account', 'I forgot my password. What should I do?',
        'Use Forgot Password on the login page. If you are already logged in, you can change it from your Profile under Change Password.'],
    ['account', 'How do I save more than one delivery address?',
        'Open Address from your profile menu and tap Add Address. You can mark one address as your default for checkout.'],
    ['food',    'Are the foods halal?',
        'Foods with a Halal Certified label have been verified. Foods may also show a BPOM or P-IRT label. Check the label on each food page.'],
    ['food',    'How do I find food near me?',
        'Use the Culinary Map to see restaurants and food centers around you, and filter by type.'],
    ['rewards', 'How do reward points work?',
        'You earn points when you order and review foods. Collect enough points to redeem vouchers on the Rewards page.'],
    ['rewards', 'Do my points expire?',
        'Please check the Rewards page for the current rules, because reward terms can change over time.'],
];

$u = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$whatsapp = '+62 812 0000 0000';   // replace with your real number
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Help Center – Kabupaten Sukabumi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--yellow:#FFC41F;--yellow-d:#F2A900;--cream:#FFF8E1;--cream-2:#FFFBEF;--ink:#1F2933;--muted:#6B7280;--line:#EEE7CF;--white:#fff}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',system-ui,sans-serif;color:var(--ink);background:linear-gradient(180deg,var(--cream-2) 0%,#FFF3C4 100%) fixed;min-height:100vh}
a{color:inherit;text-decoration:none}
.topbar{background:var(--white);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
.topbar .in{max-width:1100px;margin:auto;display:flex;align-items:center;gap:28px;padding:10px 20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;line-height:1.1}
.brand svg{width:38px;height:38px}
.nav{display:flex;gap:22px;margin-left:auto;font-size:13px;font-weight:500}
.nav a{padding:6px 0;border-bottom:2px solid transparent}
.wrap{max-width:900px;margin:0 auto;padding:0 20px 40px}
.hero{padding:44px 0 22px;text-align:center}
.hero h1{font-size:32px;line-height:1.25}
.hero p{color:var(--muted);margin:10px auto 22px;max-width:500px;font-size:14.5px;line-height:1.7}
.search{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px 16px;box-shadow:0 6px 24px rgba(180,140,20,.12);max-width:560px;margin:auto;text-align:left}
.search:focus-within{border-color:var(--yellow-d);box-shadow:0 0 0 3px rgba(255,196,31,.25)}
.search svg{width:20px;height:20px;fill:none;stroke:var(--muted);stroke-width:2;stroke-linecap:round;flex:none}
.search input{border:0;outline:0;font:inherit;font-size:15px;width:100%;background:transparent}
.tabs{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:26px 0 20px}
.tab{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:30px;padding:9px 16px;font:inherit;font-size:13.5px;font-weight:500;cursor:pointer;color:var(--ink)}
.tab svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.tab:hover{background:#FFF1BF}
.tab[aria-pressed="true"]{background:var(--yellow);border-color:var(--yellow-d);font-weight:600}
.tab:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
.box{background:#fff;border-radius:18px;box-shadow:0 6px 24px rgba(180,140,20,.12);padding:8px 26px}
details{border-bottom:1px solid var(--line)}
details:last-of-type{border-bottom:0}
summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 0;font-size:14.5px;font-weight:500}
summary::-webkit-details-marker{display:none}
summary::after{content:"+";font-size:22px;line-height:1;color:var(--yellow-d);flex:none}
details[open] summary::after{content:"\2212"}
summary:focus-visible{outline:2px solid var(--ink);outline-offset:2px;border-radius:6px}
details p{padding:0 0 18px;color:#374151;font-size:14px;line-height:1.75;max-width:70ch}
.none{padding:30px 0;text-align:center;color:var(--muted);font-size:14px}
.cta{margin-top:28px;background:var(--yellow);border-radius:18px;padding:26px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.cta h2{font-size:18px}
.cta p{font-size:13.5px;margin-top:4px}
.cta .b{display:flex;gap:10px;flex-wrap:wrap}
.cta a{background:#fff;border-radius:10px;padding:11px 20px;font-weight:600;font-size:13.5px}
.cta a:hover{background:var(--cream)}
footer{border-top:1px solid var(--line);background:#fff;padding:18px 20px;text-align:center;color:var(--muted);font-size:12.5px}
@media (max-width:820px){.nav{display:none}.hero h1{font-size:25px}.box{padding:4px 18px}}
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
      <a href="index.php">Home</a><a href="culinary.php">Culinary</a><a href="tourism.php">Tourism</a><a href="about.php">About</a><a href="contact.php">Contact</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero">
    <h1>How can we help<?= !empty($u['name']) ? ', ' . e(explode(' ', $u['name'])[0]) : '' ?>?</h1>
    <p>Find quick answers about orders, payment, your account and our foods.</p>
    <label class="search">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input id="q" type="search" placeholder="Search for help, for example: delivery" aria-label="Search help" autocomplete="off">
    </label>
  </section>

  <div class="tabs" role="group" aria-label="Help topics">
    <button class="tab" type="button" data-topic="all" aria-pressed="true">All topics</button>
    <?php foreach ($topics as $key => [$label, $icon]): ?>
      <button class="tab" type="button" data-topic="<?= e($key) ?>" aria-pressed="false">
        <svg viewBox="0 0 24 24"><path d="<?= $icon ?>"/></svg><?= e($label) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <section class="box" id="list" aria-live="polite">
    <?php foreach ($faqs as [$topic, $q, $a]): ?>
      <details data-topic="<?= e($topic) ?>">
        <summary><?= e($q) ?></summary>
        <p><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
    <div class="none" id="none" hidden>No answers found. Try another word, or contact us below.</div>
  </section>

  <section class="cta">
    <div>
      <h2>Still need help?</h2>
      <p>Our team is happy to answer your questions.</p>
    </div>
    <div class="b">
      <a href="contact.php">Send a message</a>
      <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" target="_blank" rel="noopener">WhatsApp</a>
    </div>
  </section>
</main>

<footer>Kabupaten Sukabumi · Taste, Explore, Enjoy</footer>

<script>
(function () {
  const q = document.getElementById('q'), none = document.getElementById('none');
  const items = [...document.querySelectorAll('#list details')];
  const tabs  = [...document.querySelectorAll('.tab')];
  let topic = 'all';

  function apply() {
    const term = q.value.trim().toLowerCase();
    let shown = 0;
    items.forEach(d => {
      const ok = (topic === 'all' || d.dataset.topic === topic) && (!term || d.textContent.toLowerCase().includes(term));
      d.hidden = !ok;
      if (ok) shown++;
      if (term && ok) d.open = true; else if (!term) d.open = false;
    });
    none.hidden = shown !== 0;
  }

  tabs.forEach(t => t.addEventListener('click', () => {
    topic = t.dataset.topic;
    tabs.forEach(x => x.setAttribute('aria-pressed', x === t));
    apply();
  }));
  q.addEventListener('input', apply);
})();
</script>
</body>
</html>