<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

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
    $target_url = trim($_POST['target_url'] ?? '');
    // Normalize URLs that come without a scheme (e.g., starts with www. or bare domain)
    if ($target_url !== '' && !preg_match('#^https?://#i', $target_url)) {
      if (preg_match('#^www\..+#i', $target_url) || preg_match('#^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(/.*)?$#', $target_url)) {
        $target_url = 'https://' . $target_url; // default to https
      }
    }

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
            $generatedLink = "{$base}/redirect.php?token={$token}";            $logger->info('Link created', $record);
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
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo"><div class="mark">GF</div><div class="title">Geo-Fence Link Generator</div></div>
      <div class="nav"><a href="dashboard.php"><i class='bx bx-bar-chart-alt-2'></i> Dashboard</a></div>
    </div>

    <h1><i class='bx bx-map-pin'></i> Create a Geo-Fenced Link</h1>

    <?php if ($errors): ?>
      <div class="card" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3)">
        <?php foreach($errors as $e) echo "<div><i class='bx bx-x-circle'></i> ".htmlspecialchars($e)."</div>"; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate class="card">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div id="map"></div>

      <label><i class='bx bx-map-pin'></i> Latitude</label>
      <input type="text" name="lat" id="lat" readonly required>

      <label><i class='bx bx-map-pin'></i> Longitude</label>
      <input type="text" name="lng" id="lng" readonly required>

      <label><i class='bx bx-ruler'></i> Radius (meters)</label>
      <input type="number" name="radius" id="radius" value="100" min="5" max="2000" required>

  <label><i class='bx bx-target-lock'></i> Target URL (where to redirect if inside fence)</label>
  <input type="url" name="target_url" id="target_url" placeholder="example.com/secret-page" required>
  <div id="urlHint" class="small" style="margin-top:4px;color:var(--text-muted)"></div>

      <label><i class='bx bx-time-five'></i> Expiry Date/Time</label>
      <input type="datetime-local" name="expires" required>

      <button type="button" id="useLocation"><i class='bx bx-current-location'></i> Use My Current Location</button>
      <button type="submit"><i class='bx bx-send'></i> Generate Geo-Fenced Link</button>
    </form>

    <?php if (!empty($generatedLink)): ?>
      <div class="card generated">
        <div style="flex:1">
          <p style="font-size:1.1rem;margin-bottom:12px"><strong><i class='bx bx-check-circle'></i> Link Generated Successfully!</strong></p>
          <input id="generatedLink" type="text" value="<?= htmlspecialchars($generatedLink) ?>" readonly style="width:100%">
          <div style="margin-top:12px;display:flex;gap:8px">
            <button type="button" id="copyLink" class="ghost" style="width:auto"><i class='bx bx-copy'></i> Copy Link</button>
            <a href="<?= htmlspecialchars($generatedLink) ?>" target="_blank" style="flex:1"><button type="button" style="width:100%"><i class='bx bx-link-external'></i> Open Link</button></a>
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
            alert('Link copied to clipboard!');
          });
        });
      </script>
    <?php endif; ?>

    <h2><i class='bx bx-list-ul'></i> Recent Links</h2>
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
              <i class='bx bx-fingerprint'></i> <?= htmlspecialchars(substr($link['id'], 0, 8)) ?>… | 
              <i class='bx bx-map'></i> <?= htmlspecialchars(number_format((float)$link['lat'],6)) ?>, <?= htmlspecialchars(number_format((float)$link['lng'],6)) ?> | 
              <i class='bx bx-ruler'></i> <?= htmlspecialchars((string)$link['radius']) ?>m | 
              <i class='bx bx-time'></i> <?= htmlspecialchars($link['expires']) ?>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="badge"><i class='bx bx-link-external'></i> Open</a>
            <a href="dashboard.php" class="badge"><i class='bx bx-bar-chart-alt-2'></i> Stats</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Powered by Mavis branding -->
  <div style="margin-top:40px;padding:20px;text-align:center;border-top:1px solid rgba(148,163,184,0.1)">
    <div style="display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap">
      <img src="assets/mavis.jpg" alt="Mavis Logo" style="height:40px;width:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.2)">
      <span style="color:var(--text-muted);font-size:0.9rem;font-weight:500">Powered by <strong style="color:var(--accent-purple)">Mavis API System</strong></span>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const mapEl = document.getElementById('map');
    if (mapEl) {
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
        if (!navigator.geolocation) return alert('Geolocation not supported by your browser.');
        const btn = document.getElementById('useLocation');
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Getting precise location...';
        btn.disabled = true;
        
        // Try to get high accuracy position multiple times for better precision
        let attempts = 0;
        const maxAttempts = 3;
        let bestAccuracy = Infinity;
        let bestPosition = null;
        
        function tryGetPosition() {
          navigator.geolocation.getCurrentPosition(p => {
            attempts++;
            if (p.coords.accuracy < bestAccuracy) {
              bestAccuracy = p.coords.accuracy;
              bestPosition = p;
            }
            
            // Show accuracy feedback
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Accuracy: ' + Math.round(p.coords.accuracy) + 'm (attempt ' + attempts + '/' + maxAttempts + ')';
            
            if (attempts < maxAttempts && p.coords.accuracy > 20) {
              // Try again if accuracy is poor
              setTimeout(tryGetPosition, 1000);
            } else {
              // Use best position found
              setPoint(bestPosition.coords.latitude, bestPosition.coords.longitude);
              map.setView([bestPosition.coords.latitude, bestPosition.coords.longitude], 18);
              btn.innerHTML = '<i class="bx bx-check"></i> Location Set! (±' + Math.round(bestAccuracy) + 'm)';
              setTimeout(() => { 
                btn.innerHTML = '<i class="bx bx-current-location"></i> Use My Current Location'; 
                btn.disabled = false; 
              }, 3000);
            }
          }, (err) => {
            alert('Unable to get your location. Please ensure GPS is enabled and allow location access.');
            btn.innerHTML = '<i class="bx bx-current-location"></i> Use My Current Location';
            btn.disabled = false;
          }, { 
            enableHighAccuracy: true, 
            timeout: 15000,
            maximumAge: 0
          });
        }
        
        tryGetPosition();
      });
    }

    // URL normalization hint
    const urlInput = document.getElementById('target_url');
    const urlHint = document.getElementById('urlHint');
    function updateUrlHint() {
      const raw = urlInput.value.trim();
      if (!raw) { urlHint.textContent = ''; return; }
      let normalized = raw;
      if (!/^https?:\/\//i.test(raw)) {
        if (/^www\./i.test(raw) || /^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(\/.*)?$/.test(raw)) {
          normalized = 'https://' + raw.replace(/^https?:\/\//i,'');
        }
      }
      urlHint.innerHTML = '<i class="bx bx-info-circle"></i> Normalized: <code style="color:var(--accent-cyan)">' + normalized.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</code>';
    }
    urlInput.addEventListener('input', updateUrlHint);
    urlInput.addEventListener('blur', updateUrlHint);
    updateUrlHint();
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
      if (!navigator.geolocation) return alert('Geolocation not supported by your browser.');
      
      const btn = document.getElementById('useLocation');
      btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Getting location...';
      btn.disabled = true;
      
      navigator.geolocation.getCurrentPosition(p => {
        setPoint(p.coords.latitude, p.coords.longitude);
        map.setView([p.coords.latitude, p.coords.longitude], 16);
        btn.innerHTML = '<i class="bx bx-check"></i> Location Set!';
        setTimeout(() => {
          btn.innerHTML = '<i class="bx bx-current-location"></i> Use My Current Location';
          btn.disabled = false;
        }, 2000);
      }, () => {
        alert('Unable to get your location. Please allow location access.');
        btn.innerHTML = '<i class="bx bx-current-location"></i> Use My Current Location';
        btn.disabled = false;
      }, {
        enableHighAccuracy: true,
        timeout: 10000
      });
    });
  </script>
</body>
</html>
