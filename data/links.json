<?php
// public/index.php

$linksFile = __DIR__ . '/../data/links.json';
if (!file_exists($linksFile)) {
    file_put_contents($linksFile, json_encode([], JSON_PRETTY_PRINT));
}
$links = json_decode(file_get_contents($linksFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat     = $_POST['lat'] ?? null;
    $lng     = $_POST['lng'] ?? null;
    $radius  = $_POST['radius'] ?? null;
    $expires = $_POST['expires'] ?? null;

    if ($lat && $lng && $radius && $expires) {
        $id = bin2hex(random_bytes(4));

        $links[] = [
            'id'      => $id,
            'lat'     => (float) $lat,
            'lng'     => (float) $lng,
            'radius'  => (int) $radius,
            'expires' => $expires
        ];

        file_put_contents($linksFile, json_encode($links, JSON_PRETTY_PRINT));

        $generatedLink = "http://" . $_SERVER['HTTP_HOST'] . "/redirect.php?id=" . $id;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GPS Attendance - Create Link</title>
  <link rel="stylesheet" href="assets/style.css">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <style>
    #map { height: 300px; margin-bottom: 1rem; }
    input, button { margin: 5px 0; padding: 6px; width: 100%; }
  </style>
</head>
<body>
  <h1>📍 GPS Attendance - Create Geo-Fenced Link</h1>

  <form method="POST">
    <div id="map"></div>

    <label>Latitude:</label>
    <input type="text" name="lat" id="lat" readonly required>

    <label>Longitude:</label>
    <input type="text" name="lng" id="lng" readonly required>

    <label>Radius (meters):</label>
    <input type="number" name="radius" id="radius" value="100" required>

    <label>Expiry Date/Time (UTC):</label>
    <input type="datetime-local" name="expires" required>

    <button type="button" id="useLocation">📡 Use My Live Location</button>
    <button type="submit">Generate Link</button>
  </form>

  <?php if (!empty($generatedLink)): ?>
    <p><strong>✅ Link Generated:</strong></p>
    <input type="text" value="<?= htmlspecialchars($generatedLink) ?>" readonly style="width:100%;">
  <?php endif; ?>

  <h2>Existing Links</h2>
  <ul>
    <?php foreach ($links as $link): ?>
      <li>
        ID: <?= htmlspecialchars($link['id']) ?> |
        Expires: <?= htmlspecialchars($link['expires']) ?> |
        <a href="redirect.php?id=<?= htmlspecialchars($link['id']) ?>" target="_blank">Open Link</a>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([6.5244, 3.3792], 13); // Lagos default 🌍

    // Load OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker, circle;

    // On map click, set marker + circle radius
    map.on('click', function(e) {
      const lat = e.latlng.lat.toFixed(6);
      const lng = e.latlng.lng.toFixed(6);
      document.getElementById('lat').value = lat;
      document.getElementById('lng').value = lng;

      if (marker) map.removeLayer(marker);
      if (circle) map.removeLayer(circle);

      marker = L.marker([lat, lng]).addTo(map);
      circle = L.circle([lat, lng], { radius: document.getElementById('radius').value }).addTo(map);
    });

    // Update circle radius when input changes
    document.getElementById('radius').addEventListener('input', function() {
      if (circle && marker) {
        circle.setRadius(this.value);
      }
    });

    // Use live location button
    document.getElementById('useLocation').addEventListener('click', function() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          const lat = pos.coords.latitude.toFixed(6);
          const lng = pos.coords.longitude.toFixed(6);
          document.getElementById('lat').value = lat;
          document.getElementById('lng').value = lng;

          map.setView([lat, lng], 15);

          if (marker) map.removeLayer(marker);
          if (circle) map.removeLayer(circle);

          marker = L.marker([lat, lng]).addTo(map);
          circle = L.circle([lat, lng], { radius: document.getElementById('radius').value }).addTo(map);
        });
      } else {
        alert("Geolocation not supported on this device.");
      }
    });
  </script>
</body>
</html>
