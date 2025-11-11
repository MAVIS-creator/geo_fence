<?php
// public/redirect.php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// Get JWT token from URL
$token = $_GET['token'] ?? null;

if (!$token) {
    http_response_code(400);
    die("❌ Missing token. Invalid link.");
}

// Verify and parse JWT
try {
    $claims = jwt_verify($token);
} catch (Exception $e) {
    http_response_code(401);
    $logger->warning('Invalid token attempt', ['token' => substr($token, 0, 20), 'error' => $e->getMessage()]);
    die("❌ Invalid or tampered token.");
}

// Check expiration
if (isset($claims['exp']) && $claims['exp'] < time()) {
    http_response_code(410);
    $logger->info('Expired token accessed', ['jti' => $claims['jti'] ?? 'unknown']);
    die("❌ This link has expired.");
}

// Extract geo-fence data
$targetLat = $claims['lat'] ?? null;
$targetLng = $claims['lng'] ?? null;
$radius = $claims['radius'] ?? null;
$targetUrl = $claims['target_url'] ?? null;
$linkId = $claims['jti'] ?? 'unknown';

if (!$targetLat || !$targetLng || !$radius || !$targetUrl) {
    http_response_code(400);
    die("❌ Malformed token data.");
}

// Handle AJAX location verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
    
  $userLat = $_POST['lat'] ?? null;
  $userLng = $_POST['lng'] ?? null;

  if (!$userLat || !$userLng) {
    echo json_encode(['status' => 'error', 'message' => 'Missing location data']);
    exit;
  }

  // Rate limiting by IP+link
  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $identifier = md5($ip . '|' . $linkId);
  if (!check_rate_limit($identifier, (int)($_ENV['RATE_LIMIT_MAX'] ?? 15), (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60))) {
    http_response_code(429);
    $logger->warning('Rate limit exceeded', ['id' => $identifier, 'ip' => $ip, 'link' => $linkId]);
    track_access($linkId, false, ['reason' => 'rate_limited', 'ip' => $ip]);
    echo json_encode(['status' => 'error', 'message' => 'Too many attempts. Please try again later.']);
    exit;
  }

  // Calculate distance using haversine
  $distance = haversine(
    (float)$targetLat,
    (float)$targetLng,
    (float)$userLat,
    (float)$userLng
  );

  $success = $distance <= $radius;

  // Track analytics
  track_access($linkId, $success, [
    'ip' => $ip,
    'user_lat' => (float)$userLat,
    'user_lng' => (float)$userLng,
    'distance' => round($distance, 2)
  ]);

  // Send optional notification
  send_access_notification($linkId, ['lat' => $targetLat, 'lng' => $targetLng, 'radius' => $radius, 'target_url' => $targetUrl], $success, ['lat' => $userLat, 'lng' => $userLng, 'distance' => round($distance,2)]);

  if ($success) {
    $logger->info('Geo-fence access granted', [
      'link_id' => $linkId,
      'user_lat' => $userLat,
      'user_lng' => $userLng,
      'distance' => round($distance, 2),
      'target' => $targetUrl
    ]);

    echo json_encode([
      'status' => 'success',
      'message' => '✅ Access granted! Redirecting...',
      'redirect_url' => $targetUrl
    ]);
  } else {
    $logger->warning('Geo-fence access denied', [
      'link_id' => $linkId,
      'user_lat' => $userLat,
      'user_lng' => $userLng,
      'distance' => round($distance, 2),
      'required_radius' => $radius
    ]);

    echo json_encode([
      'status' => 'error',
      'message' => "❌ You're {" . round($distance,2) . "}m away. Must be within {$radius}m."
    ]);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Geo-Fenced Link - Verifying Location</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="container" style="max-width:600px;text-align:center;margin-top:60px">
    <div style="font-size:4rem;margin-bottom:20px">🔒</div>
    <h1>Geo-Fenced Link</h1>
    <p id="status" class="loading">📡 Verifying your location...</p>
    <p class="small" style="margin-top:12px">Please allow location access when prompted</p>
  </div>

  <script>
    function verifyLocation(lat, lng) {
      const formData = new FormData();
      formData.append('lat', lat);
      formData.append('lng', lng);

      fetch(window.location.href, {
        method: "POST",
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        const statusEl = document.getElementById("status");
        statusEl.innerText = data.message;

        if (data.status === 'success') {
          statusEl.className = 'success';
          // Redirect to target URL after 2 seconds
          setTimeout(() => {
            window.location.href = data.redirect_url;
          }, 2000);
        } else {
          statusEl.className = 'error';
        }
      })
      .catch(err => {
        document.getElementById("status").innerText = "⚠️ Error verifying location: " + err.message;
        document.getElementById("status").className = 'error';
      });
    }

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        pos => {
          const lat = pos.coords.latitude.toFixed(6);
          const lng = pos.coords.longitude.toFixed(6);
          verifyLocation(lat, lng);
        },
        err => {
          const statusEl = document.getElementById("status");
          statusEl.className = 'error';
          statusEl.innerText = "❌ Location permission denied or unavailable.";
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 0
        }
      );
    } else {
      const statusEl = document.getElementById("status");
      statusEl.className = 'error';
      statusEl.innerText = "❌ Geolocation not supported by your browser.";
    }
  </script>
</body>
</html>
