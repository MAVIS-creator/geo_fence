<?php
require_once __DIR__ . '/../src/GeoFencer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat'], $_POST['lng'])) {
    $geo = new GeoFencer();
    if ($geo->isWithinGeofence($_POST['lat'], $_POST['lng'])) {
        header("Location: https://docs.google.com/forms/d/e/.../viewform"); 
        exit;
    } else {
        include __DIR__ . '/../views/error.html';
        exit;
    }
}

// Admin map view
if (isset($_GET['view']) && $_GET['view'] === 'map') {
    session_start();
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
    include __DIR__ . '/../views/map.html';
    exit;
}
