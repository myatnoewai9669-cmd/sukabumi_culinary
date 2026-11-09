/**
 * butterflies-swarm.js
 * A swarm of small butterflies that flutter around the page and fly toward the mouse / finger.
 * Click or tap to scatter them. When the pointer stops moving they drift off on their own.
 * Add ONE line before </body> of any page:  <script src="butterflies-swarm.js"></script>
 * It creates its own canvas behind the page content, so no other code needs to change.
 */
(function () {
  'use strict';

  // Respect people who turn animations off
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  // ---- Settings you can change ----
  var MIN_COUNT   = 30;       // fewest butterflies (small screens)
  var MAX_COUNT   = 90;       // most butterflies (big screens)
  var SIZE_MIN    = 0.22;     // 1 = about 80px wide
  var SIZE_MAX    = 0.55;
  var MAX_SPEED   = 380;      // pixels per second when chasing the pointer
  var IDLE_AFTER  = 2500;     // ms without pointer movement before they wander again
  var COLORFUL    = false;    // false = dark butterflies with a few bright ones, true = all bright

  var DARK   = ['#15131a', '#7f2b6e', '#a521cd', '#10192b', '#2b2b33'];
  var ACCENT = ['#d9472b', '#f08a24', '#f4c430', '#2f80ed', '#e84393'];
  var BRIGHT = ['#ff5d8f', '#ff9f1c', '#7b5cff', '#1fb6ff', '#2ec4b6', '#ffd23f', '#ef476f', '#8ac926'];

  var canvas = document.createElement('canvas');
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText =
    'position:fixed;left:0;top:0;width:100%;height:100%;z-index:-1;pointer-events:none;';
  document.body.insertBefore(canvas, document.body.firstChild);

  var ctx = canvas.getContext('2d');
  var W = 0, H = 0, dpr = 1, flies = [];
  var pointer = { x: 0, y: 0, active: false, last: 0 };
  var follow = 0;                                   // 0 = wandering, 1 = chasing the pointer

  function rand(a, b) { return a + Math.random() * (b - a); }
  function pick(a) { return a[Math.floor(Math.random() * a.length)]; }

  function makeFly() {
    var main = COLORFUL ? pick(BRIGHT) : (Math.random() < 0.22 ? pick(ACCENT) : pick(DARK));
    var a = rand(0, 6.283);
    return {
      x: rand(0, W), y: rand(0, H),
      vx: Math.cos(a) * 40, vy: Math.sin(a) * 40,
      heading: a,
      size: rand(SIZE_MIN, SIZE_MAX),
      flapSpeed: rand(14, 24),
      phase: rand(0, 6.28),
      wander: rand(0, 6.283),
      cruise: rand(40, 110),
      pull: rand(0.6, 1.4),                          // how strongly it is drawn to the pointer
      orbitR: rand(15, 150),                         // distance it keeps around the pointer
      orbitA: rand(0, 6.283),
      orbitS: rand(-2.2, 2.2),
      c1: main
    };
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = window.innerWidth; H = window.innerHeight;
    canvas.width = Math.round(W * dpr);
    canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var want = Math.max(MIN_COUNT, Math.min(MAX_COUNT, Math.round(W * H / 16000)));
    while (flies.length < want) flies.push(makeFly());
    flies.length = want;
  }

  // ---- Pointer ----
  function onMove(e) {
    pointer.x = e.clientX; pointer.y = e.clientY;
    pointer.active = true; pointer.last = performance.now();
  }
  function onDown(e) {
    onMove(e);
    for (var i = 0; i < flies.length; i++) {         // scatter away from the click
      var f = flies[i], dx = f.x - e.clientX, dy = f.y - e.clientY;
      var d = Math.sqrt(dx * dx + dy * dy) || 1;
      var push = rand(300, 650);
      f.vx += dx / d * push; f.vy += dy / d * push;
    }
  }
  window.addEventListener('pointermove', onMove, { passive: true });
  window.addEventListener('pointerdown', onDown, { passive: true });
  window.addEventListener('pointerleave', function () { pointer.active = false; });
  document.addEventListener('mouseleave', function () { pointer.active = false; });

  // ---- Drawing ----
  function wing(f) {
    ctx.beginPath();                                  // upper wing
    ctx.moveTo(0, -2);
    ctx.bezierCurveTo(8, -34, 42, -40, 35, -10);
    ctx.bezierCurveTo(32, -2, 10, 2, 0, -2);
    ctx.fill();
    ctx.beginPath();                                  // lower wing
    ctx.moveTo(0, 0);
    ctx.bezierCurveTo(24, 2, 32, 24, 17, 27);
    ctx.bezierCurveTo(6, 29, 2, 12, 0, 0);
    ctx.fill();
  }

  function drawFly(f, t) {
    var flap = 0.12 + 0.88 * Math.abs(Math.sin(t * f.flapSpeed + f.phase));
    ctx.save();
    ctx.translate(f.x, f.y);
    ctx.rotate(f.heading + Math.PI / 2);              // drawn facing up
    ctx.scale(f.size, f.size);
    ctx.fillStyle = f.c1;

    ctx.save(); ctx.scale(flap, 1); wing(f); ctx.restore();       // right
    ctx.save(); ctx.scale(-flap, 1); wing(f); ctx.restore();      // left

    ctx.fillStyle = 'rgba(255,255,255,.18)';          // soft highlight so dark wings keep some shape
    ctx.save(); ctx.scale(flap, 1);
    ctx.beginPath(); ctx.ellipse(22, -17, 7, 4, -0.6, 0, 6.283); ctx.fill();
    ctx.scale(-1, 1);
    ctx.beginPath(); ctx.ellipse(22, -17, 7, 4, -0.6, 0, 6.283); ctx.fill();
    ctx.restore();

    ctx.fillStyle = '#0c0a0e';                        // body
    ctx.beginPath(); ctx.ellipse(0, 3, 2.6, 13, 0, 0, 6.283); ctx.fill();
    ctx.restore();
  }

  // ---- Animation ----
  var last = 0;
  function frame(now) {
    var dt = Math.min((now - last) / 1000 || 0, 0.05);
    last = now;
    var t = now / 1000;

    var chasing = pointer.active && (now - pointer.last) < IDLE_AFTER;
    follow += ((chasing ? 1 : 0) - follow) * Math.min(1, dt * 2.5);

    ctx.clearRect(0, 0, W, H);

    for (var i = 0; i < flies.length; i++) {
      var f = flies[i];

      // wandering: the preferred direction slowly changes
      f.wander += rand(-1, 1) * dt * 3;
      var wx = Math.cos(f.wander) * f.cruise, wy = Math.sin(f.wander) * f.cruise;
      f.vx += (wx - f.vx) * dt * 0.8 * (1 - follow);
      f.vy += (wy - f.vy) * dt * 0.8 * (1 - follow);

      // chasing: a spring toward a point that circles around the pointer
      if (follow > 0.01) {
        f.orbitA += f.orbitS * dt;
        var tx = pointer.x + Math.cos(f.orbitA) * f.orbitR;
        var ty = pointer.y + Math.sin(f.orbitA) * f.orbitR;
        var k = 4.2 * f.pull * follow;
        f.vx += (tx - f.x) * k * dt;
        f.vy += (ty - f.y) * k * dt;
        var drag = 1 - Math.min(0.9, 2.2 * dt * follow);
        f.vx *= drag; f.vy *= drag;
        // a little jitter so the swarm looks alive
        f.vx += rand(-1, 1) * 160 * dt; f.vy += rand(-1, 1) * 160 * dt;
      }

      // speed limit
      var sp = Math.sqrt(f.vx * f.vx + f.vy * f.vy);
      if (sp > MAX_SPEED) { f.vx *= MAX_SPEED / sp; f.vy *= MAX_SPEED / sp; sp = MAX_SPEED; }

      f.x += f.vx * dt;
      f.y += f.vy * dt;

      // turn smoothly toward the direction of travel
      if (sp > 12) {
        var target = Math.atan2(f.vy, f.vx);
        var diff = Math.atan2(Math.sin(target - f.heading), Math.cos(target - f.heading));
        f.heading += diff * Math.min(1, dt * 7);
      }

      // wrap around the edges while wandering
      if (follow < 0.5) {
        if (f.x < -60) f.x = W + 60; else if (f.x > W + 60) f.x = -60;
        if (f.y < -60) f.y = H + 60; else if (f.y > H + 60) f.y = -60;
      }

      drawFly(f, t);
    }
    requestAnimationFrame(frame);
  }

  window.addEventListener('resize', resize);
  resize();
  requestAnimationFrame(frame);
})();