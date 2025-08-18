<?php
// Example simple protection
if ($_GET['view'] === 'map') {
    session_start();
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
    include __DIR__ . '/../views/map.html';
    exit;
}
