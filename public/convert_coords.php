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
$refLat = isset($input['refLat']) ? (float)$input['refLat'] : null;
$refLng = isset($input['refLng']) ? (float)$input['refLng'] : null;

// Convert Plus Code to decimal (with optional reference location for short codes)
$coords = pluscode_to_decimal($plusCode, $refLat, $refLng);

if ($coords === null) {
    http_response_code(400);
    $errorMsg = 'Invalid Plus Code format';
    
    // Check if it's a short code without reference
    try {
        if (\OpenLocationCode\OpenLocationCode::isShort($plusCode) && ($refLat === null || $refLng === null)) {
            $errorMsg = 'Short Plus Code requires a reference location (nearby coordinates)';
        }
    } catch (\Exception $e) {
        // Keep default error message
    }
    
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

echo json_encode([
    'success' => true,
    'lat' => $coords['lat'],
    'lng' => $coords['lng']
]);
