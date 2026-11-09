<?php
/**
 * ai-assistant.php
 * AI Recipe Assistant: chat page (talks to ai-chat.php).
 */
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['ai_token'])) {
    $_SESSION['ai_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['ai_token'];

$page_title  = 'AI Recipe Assistant';
$active_page = 'assistant';
require_once 'header.php';
?>

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 36px 0 26px; text-align: center; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0; color: #6b6b6b; }

    .chat-card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 24px; box-shadow: 0 14px 40px rgba(0,0,0,.1); overflow: hidden; display: flex; flex-direction: column; height: 620px; }

    .chat-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: linear-gradient(135deg, #fff3c4, #ffe08a); }
    .bot-avatar { width: 46px; height: 46px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: 0 3px 10px rgba(0,0,0,.12); animation: floaty 3s ease-in-out infinite; }
    @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    .chat-head h2 { margin: 0; font-size: 18px; flex: 1; }
    .online { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #1f7a3a; background: #d9f5e1; padding: 4px 12px; border-radius: 999px; }
    .online::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #2a9d4b; animation: pulse 1.6s infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
    .btn-new { background: rgba(255,255,255,.8); border: 0; border-radius: 10px; padding: 6px 12px; cursor: pointer; font: inherit; font-size: 13px; }
    .btn-new:hover { background: #fff; }

    .messages { flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 12px; background: #fffdf5; }
    .msg { display: flex; gap: 8px; max-width: 88%; animation: slideIn .3s ease; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    .msg.bot { align-self: flex-start; }
    .msg.user { align-self: flex-end; flex-direction: row-reverse; }
    .msg .mini { width: 32px; height: 32px; border-radius: 50%; background: #fff3c4; display: flex; align-items: center; justify-content: center; flex: none; font-size: 18px; }
    .bubble { padding: 11px 15px; border-radius: 18px; line-height: 1.5; word-break: break-word; white-space: pre-wrap; }
    .bot .bubble { background: #fff; border: 1px solid #f4ecc8; border-top-left-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
    .user .bubble { background: #f5b800; color: #222; border-top-right-radius: 4px; }
    .bubble.error { background: #ffe9e9; border-color: #f7c6c6; color: #b02020; }

    .food-reply { display: flex; gap: 12px; align-items: center; background: #fff; border: 1px solid #f4ecc8; border-radius: 16px; padding: 10px; margin-top: 8px; max-width: 420px; }
    .food-reply .thumb { width: 64px; height: 64px; border-radius: 12px; overflow: hidden; background: #fff3c4; flex: none; display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .food-reply .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .food-reply strong { display: block; }
    .food-reply small { color: #777; display: block; margin: 2px 0 6px; }
    .view-btn { display: inline-block; background: #f5b800; color: #222; padding: 6px 12px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; transition: transform .2s; }
    .view-btn:hover { transform: translateX(3px); }

    .typing { display: inline-flex; gap: 4px; padding: 14px 16px; }
    .typing span { width: 8px; height: 8px; border-radius: 50%; background: #d9b94a; animation: bounce 1s infinite; }
    .typing span:nth-child(2) { animation-delay: .15s; }
    .typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes bounce { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 18px 0; background: #fffdf5; }
    .chip { padding: 8px 14px; border-radius: 999px; background: #fff; border: 1px solid #f0e2a8; cursor: pointer; font: inherit; font-size: 13px; transition: transform .2s, background .2s; }
    .chip:hover { transform: translateY(-2px); background: #fff3c4; }

    .chat-form { display: flex; gap: 10px; padding: 14px 18px; background: #fffdf5; border-top: 1px solid #f4ecc8; }
    .chat-form input { flex: 1; padding: 13px 16px; border: 1px solid #f0e2a8; border-radius: 999px; font: inherit; background: #fff; }
    .chat-form input:focus { outline: 2px solid #f5b800; }
    .send { width: 48px; height: 48px; border-radius: 50%; border: 0; background: #f5b800; cursor: pointer; font-size: 20px; transition: transform .2s; }
    .send:hover { transform: scale(1.08) rotate(-8deg); }
    .send:disabled { opacity: .5; cursor: not-allowed; }

    @media (max-width: 600px) { .chat-card { height: calc(100vh - 160px); border-radius: 18px; } .msg { max-width: 94%; } }
</style>

<section class="page-hero">
    <div class="container">
        <h1>AI <span class="text-yellow">Recipe</span> Assistant</h1>
        <p>Tell me what you feel like eating, and I will suggest a food from Sukabumi.</p>
    </div>
</section>

<section class="section">
    <div class="container">

        <div class="chat-card">
            <div class="chat-head">
                <div class="bot-avatar">🤖</div>
                <h2>AI Recipe Assistant</h2>
                <span class="online">Online</span>
                <button type="button" class="btn-new" id="new-chat">New chat</button>
            </div>

            <div class="messages" id="messages"></div>

            <div class="chips" id="chips">
                <button type="button" class="chip">Traditional Food</button>
                <button type="button" class="chip">Spicy Food</button>
                <button type="button" class="chip">Sweet Snack</button>
                <button type="button" class="chip">Something warm</button>
            </div>

            <form class="chat-form" id="chat-form" autocomplete="off">
                <input type="text" id="input" maxlength="300" placeholder="Ask anything..." aria-label="Your message">
                <button type="submit" class="send" id="send" aria-label="Send">➤</button>
            </form>
        </div>

    </div>
</section>

<script>
(function () {
    var TOKEN    = <?= json_encode($token) ?>;
    var box      = document.getElementById('messages');
    var form     = document.getElementById('chat-form');
    var input    = document.getElementById('input');
    var sendBtn  = document.getElementById('send');
    var busy     = false;

    function scrollDown() { box.scrollTop = box.scrollHeight; }

    // Add a chat bubble. Text is added with textContent, so it is always safe.
    function addMessage(who, text, isError) {
        var row = document.createElement('div');
        row.className = 'msg ' + who;

        if (who === 'bot') {
            var mini = document.createElement('div');
            mini.className = 'mini';
            mini.textContent = '🤖';
            row.appendChild(mini);
        }

        var wrap = document.createElement('div');
        var bubble = document.createElement('div');
        bubble.className = 'bubble' + (isError ? ' error' : '');
        bubble.textContent = text;
        wrap.appendChild(bubble);
        row.appendChild(wrap);

        box.appendChild(row);
        scrollDown();
        return wrap;
    }

    function addFoodCard(wrap, food) {
        var card = document.createElement('div');
        card.className = 'food-reply';

        var thumb = document.createElement('div');
        thumb.className = 'thumb';
        if (food.image) {
            var img = document.createElement('img');
            img.src = food.image;
            img.alt = food.name;
            img.onerror = function () { thumb.textContent = '🍽️'; };
            thumb.appendChild(img);
        } else {
            thumb.textContent = '🍽️';
        }

        var info = document.createElement('div');
        var name = document.createElement('strong');
        name.textContent = food.name;
        var small = document.createElement('small');
        small.textContent = (food.text ? food.text + ' · ' : '') + food.price;
        var link = document.createElement('a');
        link.className = 'view-btn';
        link.href = 'food-detail.php?slug=' + encodeURIComponent(food.slug);
        link.textContent = 'View Recipe →';

        info.appendChild(name);
        info.appendChild(small);
        info.appendChild(link);
        card.appendChild(thumb);
        card.appendChild(info);
        wrap.appendChild(card);
    }

    function showTyping() {
        var row = document.createElement('div');
        row.className = 'msg bot';
        row.id = 'typing-row';
        row.innerHTML = '<div class="mini">🤖</div><div class="bubble"><span class="typing"><span></span><span></span><span></span></span></div>';
        box.appendChild(row);
        scrollDown();
    }
    function hideTyping() {
        var t = document.getElementById('typing-row');
        if (t) { t.remove(); }
    }

    function greeting() {
        addMessage('bot', 'Hi! 👋 What kind of food would you like today?');
    }

    function post(body) {
        body.token = TOKEN;
        return fetch('ai-chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function send(text) {
        text = (text || '').trim();
        if (text === '' || busy) { return; }

        busy = true;
        sendBtn.disabled = true;
        addMessage('user', text);
        input.value = '';
        showTyping();

        post({ message: text }).then(function (data) {
            hideTyping();
            if (data.error) {
                addMessage('bot', data.error, true);
                return;
            }
            var wrap = addMessage('bot', data.reply);
            (data.foods || []).forEach(function (f) { addFoodCard(wrap, f); });
            scrollDown();
        }).catch(function () {
            hideTyping();
            addMessage('bot', 'Sorry, something went wrong. Please try again.', true);
        }).then(function () {
            busy = false;
            sendBtn.disabled = false;
            input.focus();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        send(input.value);
    });

    document.getElementById('chips').addEventListener('click', function (e) {
        var chip = e.target.closest('.chip');
        if (chip) { send(chip.textContent); }
    });

    document.getElementById('new-chat').addEventListener('click', function () {
        post({ reset: true }).then(function () {
            box.innerHTML = '';
            greeting();
        });
    });

    greeting();
})();
</script>

<?php require_once 'footer.php'; ?>