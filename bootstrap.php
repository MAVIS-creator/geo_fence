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
use Symfony\Component\HttpFoundation\RequestStack;
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

// 4) Logger (patched to Level::Debug)
$logPath = __DIR__ . '/data/app.log';
$logger = new Logger('gps_attendance');
$logger->pushHandler(new StreamHandler($logPath, Level::Debug));

// Example log usage
$logger->debug('Logger initialized (debug mode active)');
$logger->info('Bootstrap loaded');

// 5) CSRF Manager (patched with RequestStack)
$requestStack = new RequestStack();
$csrfManager = new CsrfTokenManager(
    new UriSafeTokenGenerator(),
    new SessionTokenStorage($requestStack)
);

// Example: generate token
$csrfToken = $csrfManager->getToken('geo_form')->getValue();

// Example helper for embedding CSRF in forms
function csrf_field(string $id, CsrfTokenManager $manager): string {
    $token = $manager->getToken($id)->getValue();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// 6) Small utility helpers (validation, jwt, distance)
use Respect\Validation\Validator as v;
use Lcobucci\JWT\Configuration;

function v_lat($x){ return v::numericVal()->between(-90, 90)->validate($x); }
function v_lng($x){ return v::numericVal()->between(-180, 180)->validate($x); }
function v_radius($x){ return v::intVal()->between(5, 2000)->validate($x); } // 5m–2km sane
function v_datetime($x){ return v::stringType()->notEmpty()->validate($x); }  // parse with Carbon later

// NOTE: switched to lcobucci/jwt instead of firebase/jwt
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
    if (!$config->validator()->validate($parsed, ...[])) {
        throw new RuntimeException('Invalid JWT');
    }
    return $parsed->claims()->all();
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
