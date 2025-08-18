<?php
// public/redirect.php

$linksFile = __DIR__ . '/../data/links.json';
$links = file_exists($linksFile) ? json_decode(file_get_contents($linksFile), true) : [];

$id = $_GET['id'] ?? null;
$linkData = null;

foreach ($links as $link) {
    if ($link['id'] === $id) {
        $linkData = $link;
        break;
    }
}

if (!$linkData) {
    die("❌ Invalid or expired link.");
}

// Handle AJAX verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userLat = $_POST['lat'] ?? null;
    $userLng = $_POST['lng'] ?? null;

    if ($userLat && $userLng) {
        $distance = haversineGreatCircleDistance(
            $linkData['lat'], $linkData['lng'],
            (float)$userLat, (float)$userLng
        );

        if ($distance <= $linkData['radius']) {
            // ✅ Mark attendance in log
            $logFile = __DIR__ . '/../data/attendance.log';
            $entry = date('c') . " | ID={$id} | Lat={$userLat},Lng={$userLng} | ✅ Inside Fence\n";
            file_put_contents($logFile, $entry, FILE_APPEND);

            echo json_encode(['status' => 'success', 'message' => 'You are inside the allowed area. Attendance marked ✅']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'You are outside the allowed area ❌']);
        }
        exit;
    }
}

function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2, $earthRadius = 6371000)
{
    // convert from degrees to radians
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GPS Attendance - Verify</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <h1>📍 Verifying Your Location...</h1>
  <p id="status">Please allow location access.</p>

  <script>
    function verifyLocation(lat, lng) {
      fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "lat=" + lat + "&lng=" + lng
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById("status").innerText = data.message;
      })
      .catch(() => {
        document.getElementById("status").innerText = "⚠️ Error verifying location.";
      });
    }

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        verifyLocation(lat, lng);
      }, () => {
        document.getElementById("status").innerText = "❌ Location permission denied.";
      });
    } else {
      document.getElementById("status").innerText = "❌ Geolocation not supported.";
    }
  </script>
</body>
</html>
