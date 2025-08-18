<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lat'], $data['lng'], $data['radius'])) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

file_put_contents(__DIR__ . '/../storage/geofence.json', json_encode($data, JSON_PRETTY_PRINT));
http_response_code(200);
echo "Saved successfully";
