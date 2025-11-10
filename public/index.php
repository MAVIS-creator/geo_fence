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
  <title>GPS Attendance - Create Link</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <style>#map{height:300px;margin-bottom:1rem}</style>
</head>
<body>
  <h1>📍 GPS Attendance - Create Geo-Fenced Link</h1>

  <?php if ($errors): ?>
    <div id="status" style="background:rgba(255,0,0,.25)">
      <?php foreach($errors as $e) echo "<div>• ".htmlspecialchars($e)."</div>"; ?>
    </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div id="map"></div>

    <label>Latitude</label>
    <input type="text" name="lat" id="lat" readonly required>

    <label>Longitude</label>
    <input type="text" name="lng" id="lng" readonly required>

    <label>Radius (meters)</label>
    <input type="number" name="radius" id="radius" value="100" min="5" max="2000" required>

    <label>Target URL (where to redirect if inside fence)</label>
    <input type="url" name="target_url" placeholder="https://example.com/secret-page" required>

    <label>Expiry Date/Time</label>
    <input type="datetime-local" name="expires" required>

    <button type="button" id="useLocation">📡 Use My Live Location</button>
    <button type="submit">Generate Link</button>
  </form>

  <?php if (!empty($generatedLink)): ?>
    <p><strong>✅ Link Generated:</strong></p>
    <input type="text" value="<?= htmlspecialchars($generatedLink) ?>" readonly style="width:100%">
  <?php endif; ?>

  <h2>Existing Links</h2>
  <ul>
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
      <li>
        <span>ID: <?= htmlspecialchars(substr($link['id'], 0, 8)) ?>… | Target: <?= htmlspecialchars($link['target_url']) ?> | Expires: <?= htmlspecialchars($link['expires']) ?></span>
        <a href="<?= htmlspecialchars($url) ?>" target="_blank">Open</a>
      </li>
    <?php endforeach; ?>
  </ul>

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
      circle = L.circle([lat, lng], { radius: +document.getElementById('radius').value }).addTo(map);
    }

    map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
    document.getElementById('radius').addEventListener('input', () => { if (circle) circle.setRadius(+radius.value); });

    document.getElementById('useLocation').addEventListener('click', () => {
      if (!navigator.geolocation) return alert('Geolocation not supported.');
      navigator.geolocation.getCurrentPosition(p => {
        setPoint(p.coords.latitude, p.coords.longitude);
        map.setView([p.coords.latitude, p.coords.longitude], 16);
      }, () => alert('Unable to get location.'));
    });
  </script>
</body>
</html>
