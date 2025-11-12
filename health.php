<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Serving from PROJECT ROOT',
    'file' => __FILE__
]);
