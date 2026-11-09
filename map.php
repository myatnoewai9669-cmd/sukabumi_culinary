<?php
/**
 *map.php
 * Culinary map: Leaflet map, type filter, search, near-me and place list.
 */
require_once 'config.php';

$page_title  = 'Culinary Map';
$active_page = 'map';
require_once 'header.php';

// ---------- Load places ----------
$rows = $pdo->query(
    "SELECT id, name, type, address, latitude, longitude, rating, image
     FROM locations
     WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL
     ORDER BY name ASC"
)->fetchAll();

$places = [];
foreach ($rows as $r) {
    $places[] = [
        'id'      => (int)$r['id'],
        'name'    => $r['name'],
        'type'    => $r['type'],
        'address' => $r['address'],
        'lat'     => (float)$r['latitude'],
        'lng'     => (float)$r['longitude'],
        'rating'  => (float)$r['rating'],
        'image'   => !empty($r['image']) ? 'uploads/' . $r['image'] : '',
    ];
}
$places_json = json_encode(
    $places,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
    .page-hero { background: linear-gradient(135deg, #fff8dc, #ffe9a0); padding: 40px 0 30px; }
    .page-hero h1 { margin: 0 0 6px; }
    .page-hero p { margin: 0; color: #6b6b6b; }

    .map-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 22px 0 14px; }
    .map-tools input[type=text] {
        flex: 1; min-width: 200px; padding: 12px 16px; border: 1px solid #f0e2a8;
        border-radius: 14px; font: inherit; background: #fff;
    }
    .chip {
        padding: 9px 18px; border-radius: 999px; background: #fff; color: #444; border: 1px solid #f0e2a8;
        font: inherit; font-size: 14px; cursor: pointer; transition: transform .2s, background .2s;
    }
    .chip:hover { transform: translateY(-2px); background: #fff3c4; }
    .chip.active { background: var(--yellow, #f5b800); border-color: var(--yellow, #f5b800); color: #222; font-weight: 600; }

    .map-layout { display: grid; grid-template-columns: 340px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 850px) { .map-layout { grid-template-columns: 1fr; } }

    #map { height: 540px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,.1); z-index: 1; }
    @media (max-width: 850px) { #map { height: 380px; order: -1; } }

    .place-list { max-height: 540px; overflow-y: auto; padding-right: 4px; }
    @media (max-width: 850px) { .place-list { max-height: none; } }
    .place-item {
        display: flex; gap: 12px; align-items: center; background: #fff; border-radius: 14px; padding: 10px;
        margin-bottom: 10px; box-shadow: 0 4px 14px rgba(0,0,0,.05); cursor: pointer; transition: transform .2s;
    }
    .place-item:hover { transform: translateX(4px); }
    .place-thumb { width: 54px; height: 54px; border-radius: 12px; overflow: hidden; background: #fff3c4; flex: none; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    .place-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .place-item h4 { margin: 0 0 2px; font-size: 15px; }
    .place-item small { color: #777; display: block; }
    .empty-note { text-align: center; color: #888; padding: 30px 10px; }

    .pin span {
        display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;
        background: #f5b800; border: 3px solid #fff; border-radius: 50% 50% 50% 0; transform: rotate(-45deg);
        box-shadow: 0 3px 8px rgba(0,0,0,.3); font-size: 16px;
    }
    .pin span i { transform: rotate(45deg); font-style: normal; }
    .popup-img { width: 100%; height: 90px; object-fit: cover; border-radius: 8px; margin-bottom: 6px; }
</style>

<section class="page-hero">
    <div class="container">
        <h1 class="reveal">Culinary <span class="text-yellow">Map</span></h1>
        <p class="reveal delay-1">Find restaurants, food centers and local sellers around Sukabumi.</p>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- ================= TOOLS ================= -->
        <div class="map-tools">
            <input type="text" id="search" placeholder="Search location..." aria-label="Search location">
            <button type="button" class="chip" id="near-me">📍 Near me</button>
        </div>
        <div class="map-tools" style="margin-top:0;" id="chips">
            <button type="button" class="chip active" data-type="All">All</button>
            <button type="button" class="chip" data-type="Restaurant">🍽️ Restaurant</button>
            <button type="button" class="chip" data-type="Food Center">🍜 Food Center</button>
            <button type="button" class="chip" data-type="UMKM">🛍️ UMKM</button>
        </div>

        <!-- ================= MAP + LIST ================= -->
        <div class="map-layout">
            <div class="place-list" id="list"></div>
            <div id="map"></div>
        </div>

    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var PLACES = <?= $places_json ?>;
    var ICONS  = { 'Restaurant': '🍽️', 'Food Center': '🍜', 'UMKM': '🛍️' };

    var currentType = 'All';
    var query       = '';
    var userPos     = null;
    var userMarker  = null;
    var markers     = {};

    // Escape text before putting it into HTML
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Distance in km between two points (Haversine)
    function distanceKm(lat1, lng1, lat2, lng2) {
        var R = 6371, rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // ---------- Map ----------
    var map = L.map('map').setView([-6.9277, 106.93], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    var layer = L.layerGroup().addTo(map);

    function popupHtml(p) {
        var html = '';
        if (p.image) { html += '<img class="popup-img" src="' + esc(p.image) + '" alt="" onerror="this.style.display=\'none\'">'; }
        html += '<strong>' + esc(p.name) + '</strong><br>';
        html += '★ ' + p.rating.toFixed(1) + ' &middot; ' + esc(p.type) + '<br>';
        html += '<small>' + esc(p.address) + '</small>';
        if (p.dist != null) { html += '<br><small>📍 ' + p.dist.toFixed(1) + ' km</small>'; }
        return html;
    }

    function visiblePlaces() {
        var q = query.toLowerCase();
        var list = PLACES.filter(function (p) {
            var okType = currentType === 'All' || p.type === currentType;
            var okText = q === '' ||
                (p.name || '').toLowerCase().indexOf(q) !== -1 ||
                (p.address || '').toLowerCase().indexOf(q) !== -1;
            return okType && okText;
        });
        list.forEach(function (p) {
            p.dist = userPos ? distanceKm(userPos.lat, userPos.lng, p.lat, p.lng) : null;
        });
        if (userPos) {
            list.sort(function (a, b) { return a.dist - b.dist; });
        }
        return list;
    }

    function render(fit) {
        var list = visiblePlaces();
        layer.clearLayers();
        markers = {};

        var listEl = document.getElementById('list');
        if (list.length === 0) {
            listEl.innerHTML = '<div class="empty-note">🗺️<br>No place found.</div>';
            return;
        }

        var html = '';
        var bounds = [];
        list.forEach(function (p) {
            var icon = L.divIcon({
                className: 'pin',
                html: '<span><i>' + (ICONS[p.type] || '📍') + '</i></span>',
                iconSize: [34, 34], iconAnchor: [17, 34], popupAnchor: [0, -30]
            });
            var m = L.marker([p.lat, p.lng], { icon: icon }).bindPopup(popupHtml(p));
            m.addTo(layer);
            markers[p.id] = m;
            bounds.push([p.lat, p.lng]);

            html += '<div class="place-item" data-id="' + p.id + '">' +
                '<div class="place-thumb">' +
                    (p.image ? '<img src="' + esc(p.image) + '" alt="" onerror="this.outerHTML=\'' + (ICONS[p.type] || '📍') + '\'">' : (ICONS[p.type] || '📍')) +
                '</div>' +
                '<div><h4>' + esc(p.name) + '</h4>' +
                '<small>★ ' + p.rating.toFixed(1) + ' &middot; ' + esc(p.type) + '</small>' +
                '<small>' + (p.dist != null ? '📍 ' + p.dist.toFixed(1) + ' km &middot; ' : '') + esc(p.address) + '</small></div>' +
                '</div>';
        });
        listEl.innerHTML = html;

        if (fit && bounds.length) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
        }
    }

    // ---------- Events ----------
    document.getElementById('chips').addEventListener('click', function (e) {
        var btn = e.target.closest('.chip');
        if (!btn) { return; }
        document.querySelectorAll('#chips .chip').forEach(function (c) { c.classList.remove('active'); });
        btn.classList.add('active');
        currentType = btn.dataset.type;
        render(true);
    });

    document.getElementById('search').addEventListener('input', function () {
        query = this.value.trim();
        render(true);
    });

    document.getElementById('list').addEventListener('click', function (e) {
        var item = e.target.closest('.place-item');
        if (!item) { return; }
        var m = markers[item.dataset.id];
        if (m) {
            map.flyTo(m.getLatLng(), 16);
            m.openPopup();
            document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    document.getElementById('near-me').addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Your browser does not support location.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
            userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            if (userMarker) { map.removeLayer(userMarker); }
            userMarker = L.circleMarker([userPos.lat, userPos.lng], {
                radius: 9, color: '#fff', weight: 3, fillColor: '#1a73e8', fillOpacity: 1
            }).addTo(map).bindPopup('You are here');
            render(false);
            map.flyTo([userPos.lat, userPos.lng], 14);
        }, function () {
            alert('Could not get your location. Please allow location access.');
        });
    });

    render(true);
})();
</script>

<?php require_once 'footer.php'; ?>