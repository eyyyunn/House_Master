<?php
session_start();

// Require admin login
if (!isset($_SESSION["admin_id"])) {
    http_response_code(403); 
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}


$last_known_update = isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : 0;


$last_server_update = isset($_SESSION['last_update_timestamp']) ? (int)$_SESSION['last_update_timestamp'] : 0;

$response = [
    'refresh' => false,
    'new_timestamp' => $last_server_update
];


if ($last_server_update > $last_known_update) {
    $response['refresh'] = true;
}

header('Content-Type: application/json');
echo json_encode($response);
exit();