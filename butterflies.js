/**
 * butterflies.js
 * Animated butterflies flying in the page background.
 * They wander randomly and fly away from the mouse / finger.
 */
(function () {
    'use strict';

    // Respect users who turned animations off
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var W = window.innerWidth;
    var H = window.innerHeight;
    var COUNT = W < 768 ? 5 : 9;

    // [main wing color, accent color]
    var PALETTES = [
        ['#f9c80e', '#ff9f1c'],
        ['#ffd166', '#ef476f'],
        ['#ffe28a', '#f4a261'],
        ['#ffcf33', '#e76f51'],
        ['#fff0a8', '#f9c80e']
    ];

    var layer = document.createElement('div');
    layer.className = 'butterfly-layer';
    layer.setAttribute('aria-hidden', 'true');
    document.body.appendChild(layer);

    // ---------- Pointer tracking ----------
    var pointer = { x: -1000, y: -1000 };

    window.addEventListener('mousemove', function (e) {
        pointer.x = e.clientX;
        pointer.y = e.clientY;
    });
    window.addEventListener('touchstart', function (e) {
        pointer.x = e.touches[0].clientX;
        pointer.y = e.touches[0].clientY;
    }, { passive: true });
    window.addEventListener('touchend', function () {
        pointer.x = -1000;
        pointer.y = -1000;
    });
    window.addEventListener('resize', function () {
        W = window.innerWidth;
        H = window.innerHeight;
    });

    // ---------- Butterfly SVG ----------
    function butterflySvg(c1, c2) {
        return '' +
            '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">' +
            '<g class="wing wing-l">' +
                '<path d="M32 30 C18 6, 2 8, 4 24 C5 34, 22 36, 32 32 Z" fill="' + c1 + '"/>' +
                '<path d="M32 33 C20 34, 8 42, 14 54 C20 60, 30 48, 32 36 Z" fill="' + c2 + '"/>' +
            '</g>' +
            '<g class="wing wing-r">' +
                '<path d="M32 30 C46 6, 62 8, 60 24 C59 34, 42 36, 32 32 Z" fill="' + c1 + '"/>' +
                '<path d="M32 33 C44 34, 56 42, 50 54 C44 60, 34 48, 32 36 Z" fill="' + c2 + '"/>' +
            '</g>' +
            '<ellipse cx="32" cy="34" rx="2.6" ry="13" fill="#3b2a14"/>' +
            '<path d="M31 22 Q27 12 23 10 M33 22 Q37 12 41 10" stroke="#3b2a14" ' +
                'stroke-width="1.2" fill="none" stroke-linecap="round"/>' +
            '</svg>';
    }

    // ---------- Create butterflies ----------
    var butterflies = [];

    for (var i = 0; i < COUNT; i++) {
        var palette = PALETTES[i % PALETTES.length];
        var size = 28 + Math.random() * 26;

        var el = document.createElement('div');
        el.className = 'butterfly';
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        el.style.setProperty('--flap', (0.16 + Math.random() * 0.18) + 's');
        el.innerHTML = butterflySvg(palette[0], palette[1]);
        layer.appendChild(el);

        butterflies.push({
            el: el,
            size: size,
            x: Math.random() * W,
            y: Math.random() * H,
            angle: Math.random() * Math.PI * 2,
            speed: 0.6 + Math.random() * 0.9,
            turn: 0,
            phase: Math.random() * 100
        });
    }

    // Shortest difference between two angles
    function angleDiff(a, b) {
        var d = a - b;
        while (d > Math.PI)  { d -= Math.PI * 2; }
        while (d < -Math.PI) { d += Math.PI * 2; }
        return d;
    }

    // ---------- Animation loop ----------
    var last = performance.now();

    function frame(now) {
        var dt = Math.min((now - last) / 16.67, 3);
        last = now;

        butterflies.forEach(function (b) {
            // Random wandering
            b.phase += 0.03 * dt;
            b.turn += (Math.random() - 0.5) * 0.04 * dt;
            b.turn *= 0.96;
            b.angle += (b.turn + Math.sin(b.phase) * 0.015) * dt;

            var speed = b.speed;

            // Fly away from the pointer
            var dx = b.x - pointer.x;
            var dy = b.y - pointer.y;
            var dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < 150) {
                var away = Math.atan2(dy, dx);
                b.angle += angleDiff(away, b.angle) * 0.12 * dt;
                speed *= 1 + (150 - dist) / 60;
            }

            // Move forward with a small up/down flutter
            b.x += Math.cos(b.angle) * speed * 1.4 * dt;
            b.y += Math.sin(b.angle) * speed * 1.4 * dt + Math.sin(b.phase * 3) * 0.4;

            // Wrap around the screen edges
            var margin = 60;
            if (b.x < -margin)    { b.x = W + margin; }
            if (b.x > W + margin) { b.x = -margin; }
            if (b.y < -margin)    { b.y = H + margin; }
            if (b.y > H + margin) { b.y = -margin; }

            b.el.style.transform =
                'translate(' + (b.x - b.size / 2) + 'px,' + (b.y - b.size / 2) + 'px) ' +
                'rotate(' + (b.angle + Math.PI / 2) + 'rad)';
        });

        requestAnimationFrame(frame);
    }

    requestAnimationFrame(frame);
})();