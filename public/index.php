<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Ramsey\Uuid\Uuid;
use Carbon\Carbon;
use Symfony\Component\Security\Csrf\CsrfToken;

// Load existing links
$links = load_links();

// CSRF token for form
$csrfId = 'create_link';
$csrfToken = $csrfManager->getToken($csrfId)->getValue();

$generatedLink = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $postedToken = $_POST['_token'] ?? '';
    $csrfTokenObj = new CsrfToken($csrfId, $postedToken);
    if (!$csrfManager->isTokenValid($csrfTokenObj)) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        // Inputs
        $lat        = $_POST['lat'] ?? null;
        $lng        = $_POST['lng'] ?? null;
        $radius     = $_POST['radius'] ?? null;
        $expires    = $_POST['expires'] ?? null;
        $target_url = $_POST['target_url'] ?? null;

        // Validate inputs
        if (!v_lat($lat))      $errors[] = 'Latitude invalid.';
        if (!v_lng($lng))      $errors[] = 'Longitude invalid.';
        if (!v_radius($radius)) $errors[] = 'Radius must be 5–2000 meters.';
        if (!v_datetime($expires)) $errors[] = 'Expiry is required.';
        if (empty($target_url) || !filter_var($target_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Valid target URL is required.';
        }

        // Parse expiry into UTC ISO8601
        try {
            $exp = Carbon::parse($expires, $_ENV['TIMEZONE'] ?? 'UTC')->utc();
            if ($exp->isPast()) $errors[] = 'Expiry cannot be in the past.';
        } catch (Exception $e) {
            $errors[] = 'Invalid expiry date.';
        }

        if (!$errors) {
            // Create record + save
            $id = Uuid::uuid4()->toString();
            $record = [
                'id'         => $id,
                'lat'        => (float)$lat,
                'lng'        => (float)$lng,
                'radius'     => (int)$radius,
                'target_url' => $target_url,
                'expires'    => $exp->toIso8601String(),
                'created'    => Carbon::now('UTC')->toIso8601String()
            ];
            $links[] = $record;
            save_links($links);

            // JWT for link - embed geo-fence data in the token
            $token = jwt_sign([
                'sub'        => 'geo-fence-link',
                'jti'        => $id,
                'lat'        => (float)$lat,
                'lng'        => (float)$lng,
                'radius'     => (int)$radius,
                'target_url' => $target_url,
                'exp'        => $exp->getTimestamp()
            ]);

            $base = rtrim($_ENV['APP_URL'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . $_SERVER['HTTP_HOST']), '/');
            $generatedLink = "{$base}/redirect.php?token={$token}";

            $logger->info('Link created', $record);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Geo-Fence Link Generator</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo"><div class="mark">GF</div><div class="title">Geo-Fence Link Generator</div></div>
      <div class="nav"><a href="dashboard.php">📊 Dashboard</a></div>
    </div>

    <h1>📍 Create a Geo-Fenced Link</h1>

    <?php if ($errors): ?>
      <div class="card" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3)">
        <?php foreach($errors as $e) echo "<div>❌ ".htmlspecialchars($e)."</div>"; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate class="card">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div id="map"></div>

      <label>📍 Latitude</label>
      <input type="text" name="lat" id="lat" readonly required>

      <label>📍 Longitude</label>
      <input type="text" name="lng" id="lng" readonly required>

      <label>📏 Radius (meters)</label>
      <input type="number" name="radius" id="radius" value="100" min="5" max="2000" required>

      <label>🎯 Target URL (where to redirect if inside fence)</label>
      <input type="url" name="target_url" placeholder="https://example.com/secret-page" required>

      <label>⏰ Expiry Date/Time</label>
      <input type="datetime-local" name="expires" required>

      <button type="button" id="useLocation">📡 Use My Current Location</button>
      <button type="submit">🚀 Generate Geo-Fenced Link</button>
    </form>

    <?php if (!empty($generatedLink)): ?>
      <div class="card generated">
        <div style="flex:1">
          <p style="font-size:1.1rem;margin-bottom:12px"><strong>✅ Link Generated Successfully!</strong></p>
          <input id="generatedLink" type="text" value="<?= htmlspecialchars($generatedLink) ?>" readonly style="width:100%">
          <div style="margin-top:12px;display:flex;gap:8px">
            <button type="button" id="copyLink" class="ghost" style="width:auto">📋 Copy Link</button>
            <a href="<?= htmlspecialchars($generatedLink) ?>" target="_blank" style="flex:1"><button type="button" style="width:100%">🔗 Open Link</button></a>
          </div>
        </div>
        <div class="qr">
          <img src="<?= htmlspecialchars(generate_qr_code_url($generatedLink)) ?>" alt="QR Code" width="120" height="120">
        </div>
      </div>
      <script>
        document.getElementById('copyLink').addEventListener('click', () => {
          const el = document.getElementById('generatedLink');
          navigator.clipboard.writeText(el.value).then(()=>{
            alert('✅ Link copied to clipboard!');
          });
        });
      </script>
    <?php endif; ?>

    <h2>📝 Recent Links</h2>
    <?php if (empty($links)): ?>
      <p class="small" style="text-align:center;padding:20px">No links created yet. Generate your first geo-fenced link above!</p>
    <?php else: ?>
      <?php foreach (array_reverse($links) as $link): ?>
        <?php
          $token = jwt_sign([
              'sub'        => 'geo-fence-link',
              'jti'        => $link['id'],
              'lat'        => $link['lat'],
              'lng'        => $link['lng'],
              'radius'     => $link['radius'],
              'target_url' => $link['target_url'],
              'exp'        => strtotime($link['expires'])
          ]);
          $url = rtrim($_ENV['APP_URL'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http').'://'.$_SERVER['HTTP_HOST']), '/') 
                 . '/redirect.php?token=' . $token;
        ?>
        <div class="card link-row">
          <div>
            <strong><?= htmlspecialchars($link['target_url']) ?></strong>
            <div class="small">
              🆔 <?= htmlspecialchars(substr($link['id'], 0, 8)) ?>… | 
              📍 <?= htmlspecialchars($link['lat']) ?>, <?= htmlspecialchars($link['lng']) ?> | 
              📏 <?= htmlspecialchars($link['radius']) ?>m | 
              ⏰ <?= htmlspecialchars($link['expires']) ?>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="badge">🔗 Open</a>
            <a href="dashboard.php" class="badge">📊 Stats</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([6.5244, 3.3792], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);
    let marker, circle;

    function setPoint(lat, lng) {
      document.getElementById('lat').value = lat.toFixed(6);
      document.getElementById('lng').value = lng.toFixed(6);
      if (marker) map.removeLayer(marker);
      if (circle) map.removeLayer(circle);
      marker = L.marker([lat, lng]).addTo(map);
      circle = L.circle([lat, lng], { radius: +document.getElementById('radius').value, color: '#7c3aed', fillColor: '#7c3aed', fillOpacity: 0.2 }).addTo(map);
    }

    map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
    document.getElementById('radius').addEventListener('input', () => { if (circle) circle.setRadius(+radius.value); });

    document.getElementById('useLocation').addEventListener('click', () => {
      if (!navigator.geolocation) return alert('❌ Geolocation not supported by your browser.');
      
      const btn = document.getElementById('useLocation');
      btn.textContent = '⏳ Getting location...';
      btn.disabled = true;
      
      navigator.geolocation.getCurrentPosition(p => {
        setPoint(p.coords.latitude, p.coords.longitude);
        map.setView([p.coords.latitude, p.coords.longitude], 16);
        btn.textContent = '✅ Location Set!';
        setTimeout(() => {
          btn.textContent = '📡 Use My Current Location';
          btn.disabled = false;
        }, 2000);
      }, () => {
        alert('❌ Unable to get your location. Please allow location access.');
        btn.textContent = '📡 Use My Current Location';
        btn.disabled = false;
      }, {
        enableHighAccuracy: true,
        timeout: 10000
      });
    });
  </script>
</body>
</html>
