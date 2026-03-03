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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['tenant_id'], $_POST['message'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $message = trim($_POST['message']);

    if (empty($message) || $tenant_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input.']);
        exit();
    }

    // Security check: Verify the admin owns this tenant
    $verify_stmt = $conn->prepare("SELECT id FROM tenants WHERE id = ? AND admin_id = ?");
    $verify_stmt->bind_param("ii", $tenant_id, $admin_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_id, message) VALUES ('admin', ?, ?, ?)");
        $stmt->bind_param("iis", $admin_id, $tenant_id, $message);
        $stmt->execute();
        $new_id = $stmt->insert_id;

        // Notify all tabs of the update
        $conn->query("UPDATE system_state SET state_value = UNIX_TIMESTAMP() WHERE state_key = 'last_update_timestamp'");

        echo json_encode(['success' => true, 'id' => $new_id, 'message' => htmlspecialchars($message), 'created_at' => date("h:i A")]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied.']);
    }
    exit();
}