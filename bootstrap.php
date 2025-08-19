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
