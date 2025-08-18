<?php
// bootstrap.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Carbon\Carbon;

// 1) ENV
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// 2) Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'UTC');

// 3) Sessions (required for CSRF)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 4) Logger
$logPath = __DIR__ . '/data/app.log';
$logger = new Logger('gps_attendance');
$logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));

// 5) CSRF Manager
$csrfManager = new CsrfTokenManager(
    new UriSafeTokenGenerator(),
    new SessionTokenStorage()
);

// 6) Small utility helpers (validation, jwt, distance)
use Respect\Validation\Validator as v;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function v_lat($x){ return v::numericVal()->between(-90, 90)->validate($x); }
function v_lng($x){ return v::numericVal()->between(-180, 180)->validate($x); }
function v_radius($x){ return v::intVal()->between(5, 2000)->validate($x); } // 5m–2km sane
function v_datetime($x){ return v::stringType()->notEmpty()->validate($x); }  // parse with Carbon later

function jwt_sign(array $claims): string {
    $secret = $_ENV['JWT_SECRET'] ?? 'dev-secret';
    return JWT::encode($claims, $secret, 'HS256');
}
function jwt_verify(string $token): array {
    $secret = $_ENV['JWT_SECRET'] ?? 'dev-secret';
    return (array) JWT::decode($token, new Key($secret, 'HS256'));
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2, int $R = 6371000): float {
    $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1); $Δλ = deg2rad($lon2 - $lon1);
    $a = sin($Δφ/2)**2 + cos($φ1)*cos($φ2)*sin($Δλ/2)**2;
    $c = 2 * asin(min(1, sqrt($a)));
    return $R * $c; // meters
}

// 7) JSON store helpers
function links_path(): string { return __DIR__ . '/data/links.json'; }
function load_links(): array {
    $f = links_path();
    if (!file_exists($f)) file_put_contents($f, json_encode([], JSON_PRETTY_PRINT));
    return json_decode(file_get_contents($f), true) ?: [];
}
function save_links(array $arr): void {
    file_put_contents(links_path(), json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
