<?php
require __DIR__ . '/../bootstrap.php';

$base = rtrim($_ENV['APP_URL'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
if (str_ends_with($base, '/public')) {
    $base = substr($base, 0, -7);
}

$claims = [
    'sub'        => 'geo-fence-link',
    'jti'        => 'test-' . bin2hex(random_bytes(4)),
    'lat'        => 8.1596,
    'lng'        => 4.25867,
    'radius'     => 100,
    'target_url' => 'https://www.google.com',
    'exp'        => time() + 1800,
];

$token = jwt_sign($claims);
$url = $base . '/redirect.php?token=' . $token;

echo $url, PHP_EOL;

$headers = @get_headers($url);
if ($headers) {
    echo $headers[0], PHP_EOL;
    // Optionally show location if 301/302
    foreach ($headers as $h) {
        if (stripos($h, 'Location:') === 0) {
            echo $h, PHP_EOL;
        }
    }
}
