<?php
// bootstrap.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\HttpFoundation\Session\Session;
use Carbon\Carbon;

// 1) ENV
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// 2) Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'UTC');

// 3) Start native PHP session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 4) Symfony session wrapper (required for CSRF)
$session = new Session();
$session->start();

// 5) Logger
$logPath = __DIR__ . '/data/app.log';
$logger = new Logger('gps_attendance');
$logger->pushHandler(new StreamHandler($logPath, Level::Debug));

$logger->debug('Logger initialized (debug mode active)');
$logger->info('Bootstrap loaded');

// 6) CSRF Manager
$csrfManager = new CsrfTokenManager(
    new UriSafeTokenGenerator(),
    new SessionTokenStorage($session)
);

// Helper for embedding CSRF in forms
function csrf_field(string $id, CsrfTokenManager $manager): string {
    $token = $manager->getToken($id)->getValue();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// 7) Validation helpers
use Respect\Validation\Validator as v;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\Plain;

function v_lat($x){ return v::numericVal()->between(-90, 90)->validate($x); }
function v_lng($x){ return v::numericVal()->between(-180, 180)->validate($x); }
function v_radius($x){ return v::intVal()->between(5, 2000)->validate($x); }
function v_datetime($x){ return v::stringType()->notEmpty()->validate($x); }

// 8) JWT helpers
function jwt_config(): Configuration {
    $secret = $_ENV['JWT_SECRET'] ?? 'dev-secret';
    return Configuration::forSymmetricSigner(
        new \Lcobucci\JWT\Signer\Hmac\Sha256(),
        \Lcobucci\JWT\Signer\Key\InMemory::plainText($secret)
    );
}

function jwt_sign(array $claims): string {
    $config = jwt_config();
    $now = new DateTimeImmutable();
    $builder = $config->builder()->issuedAt($now);

    foreach ($claims as $k => $v) {
        $builder = $builder->withClaim($k, $v);
    }

    return $builder->getToken($config->signer(), $config->signingKey())->toString();
}

function jwt_verify(string $token): array {
    $config = jwt_config();
    $parsed = $config->parser()->parse($token);

    if (!$parsed instanceof Plain) {
        throw new RuntimeException('Invalid JWT format');
    }

    return $parsed->claims()->all();
}

// 9) Haversine distance helper
function haversine(float $lat1, float $lon1, float $lat2, float $lon2, int $R = 6371000): float {
    $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1); $Δλ = deg2rad($lon2 - $lon1);
    $a = sin($Δφ/2)**2 + cos($φ1)*cos($φ2)*sin($Δλ/2)**2;
    $c = 2 * asin(min(1, sqrt($a)));
    return $R * $c;
}

// 10) JSON store helpers
function links_path(): string { return __DIR__ . '/data/links.json'; }
function load_links(): array {
    $f = links_path();
    if (!file_exists($f)) file_put_contents($f, json_encode([], JSON_PRETTY_PRINT));
    return json_decode(file_get_contents($f), true) ?: [];
}
function save_links(array $arr): void {
    file_put_contents(links_path(), json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// 11) Analytics helpers
function analytics_path(): string { return __DIR__ . '/data/analytics.json'; }
function load_analytics(): array {
    $f = analytics_path();
    if (!file_exists($f)) file_put_contents($f, json_encode([], JSON_PRETTY_PRINT));
    return json_decode(file_get_contents($f), true) ?: [];
}
function save_analytics(array $arr): void {
    file_put_contents(analytics_path(), json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function track_access(string $linkId, bool $success, array $data = []): void {
    global $logger;
    $analytics = load_analytics();
    
    if (!isset($analytics[$linkId])) {
        $analytics[$linkId] = [
            'total_attempts' => 0,
            'successful_access' => 0,
            'failed_access' => 0,
            'first_access' => null,
            'last_access' => null,
            'access_log' => []
        ];
    }
    
    $analytics[$linkId]['total_attempts']++;
    if ($success) {
        $analytics[$linkId]['successful_access']++;
    } else {
        $analytics[$linkId]['failed_access']++;
    }
    
    $now = Carbon::now('UTC')->toIso8601String();
    if (!$analytics[$linkId]['first_access']) {
        $analytics[$linkId]['first_access'] = $now;
    }
    $analytics[$linkId]['last_access'] = $now;
    
    // Keep last 50 access logs per link
    $analytics[$linkId]['access_log'][] = array_merge([
        'timestamp' => $now,
        'success' => $success
    ], $data);
    
    if (count($analytics[$linkId]['access_log']) > 50) {
        $analytics[$linkId]['access_log'] = array_slice($analytics[$linkId]['access_log'], -50);
    }
    
    save_analytics($analytics);
    $logger->info('Access tracked', ['link_id' => $linkId, 'success' => $success]);
}

// 12) Rate limiting
function rate_limit_path(): string { return __DIR__ . '/data/rate_limits.json'; }
function load_rate_limits(): array {
    $f = rate_limit_path();
    if (!file_exists($f)) file_put_contents($f, json_encode([], JSON_PRETTY_PRINT));
    return json_decode(file_get_contents($f), true) ?: [];
}
function save_rate_limits(array $arr): void {
    file_put_contents(rate_limit_path(), json_encode($arr, JSON_PRETTY_PRINT));
}

function check_rate_limit(string $identifier, int $maxAttempts = 10, int $windowSeconds = 60): bool {
    $limits = load_rate_limits();
    $now = time();
    
    // Clean old entries
    foreach ($limits as $key => $data) {
        if (($now - $data['window_start']) > $windowSeconds) {
            unset($limits[$key]);
        }
    }
    
    if (!isset($limits[$identifier])) {
        $limits[$identifier] = [
            'attempts' => 1,
            'window_start' => $now
        ];
        save_rate_limits($limits);
        return true;
    }
    
    $limits[$identifier]['attempts']++;
    save_rate_limits($limits);
    
    return $limits[$identifier]['attempts'] <= $maxAttempts;
}

// 13) Email notification helper
function send_access_notification(string $linkId, array $linkData, bool $success, array $location = []): void {
    global $logger;
    
    $toEmail = $_ENV['NOTIFICATION_EMAIL'] ?? null;
    if (!$toEmail) return;
    
    $subject = $success ? "✅ Geo-Fence Access Granted" : "❌ Geo-Fence Access Denied";
    $status = $success ? "GRANTED" : "DENIED";
    $emoji = $success ? "✅" : "❌";
    
    $message = "
Geo-Fence Link Access Alert {$emoji}

Link ID: {$linkId}
Status: {$status}
Target URL: {$linkData['target_url']}
User Location: " . ($location['lat'] ?? 'N/A') . ", " . ($location['lng'] ?? 'N/A') . "
Distance: " . ($location['distance'] ?? 'N/A') . "m
Time: " . date('Y-m-d H:i:s') . " UTC

Fence Center: {$linkData['lat']}, {$linkData['lng']}
Fence Radius: {$linkData['radius']}m
";
    
    $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
    $headers .= "Reply-To: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (mail($toEmail, $subject, $message, $headers)) {
        $logger->info('Email notification sent', ['to' => $toEmail, 'link_id' => $linkId]);
    } else {
        $logger->warning('Email notification failed', ['to' => $toEmail, 'link_id' => $linkId]);
    }
}

// 14) QR Code generator (using Google Charts API as fallback)
function generate_qr_code_url(string $data): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($data);
}
