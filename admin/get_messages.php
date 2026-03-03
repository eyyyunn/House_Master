<?php
session_start();
include __DIR__ . "/../config.php";

header('Content-Type: application/json');

if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

if ($tenant_id === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Tenant ID not specified.']);
    exit();
}

if ($tenant_id == -1) {
    // System messages
    $stmt = $conn->prepare("SELECT id, sender_type, message, created_at FROM messages WHERE receiver_id = ? AND sender_type = 'system' ORDER BY created_at ASC");
    $stmt->bind_param("i", $admin_id);
} else {
    // Tenant messages
    $stmt = $conn->prepare("
        SELECT id, sender_type, message, created_at 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("iiii", $admin_id, $tenant_id, $tenant_id, $admin_id);
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['created_at_formatted'] = date("h:i A", strtotime($row['created_at']));
    $messages[] = $row;
}

echo json_encode($messages);
exit();