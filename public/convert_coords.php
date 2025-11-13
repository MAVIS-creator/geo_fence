<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['plusCode'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Plus Code is required']);
    exit;
}

$plusCode = trim($input['plusCode']);

// Convert Plus Code to decimal
$coords = pluscode_to_decimal($plusCode);

if ($coords === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Plus Code format']);
    exit;
}

echo json_encode([
    'success' => true,
    'lat' => $coords['lat'],
    'lng' => $coords['lng']
]);
