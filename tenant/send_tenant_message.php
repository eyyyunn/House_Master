<?php
session_start();
include __DIR__ . "/../config.php";

// Security: Ensure a tenant is logged in
if (!isset($_SESSION["tenant_id"])) {
    http_response_code(403);
    die(json_encode(["error" => "Access Denied"]));
}

$tenant_id = $_SESSION["tenant_id"];
$message_to_send = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message_to_send)) {
    http_response_code(400);
    die(json_encode(["error" => "Message cannot be empty."]));
}

// Get admin_id for the tenant
$tenant_stmt = $conn->prepare("SELECT admin_id FROM tenants WHERE id = ?");
$tenant_stmt->bind_param("i", $tenant_id);
$tenant_stmt->execute();
$tenant = $tenant_stmt->get_result()->fetch_assoc();
$admin_id = $tenant['admin_id'];

if ($admin_id) {
    $insert_msg_stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_id, message) VALUES ('tenant', ?, ?, ?)");
    $insert_msg_stmt->bind_param("iis", $tenant_id, $admin_id, $message_to_send);
    
    if ($insert_msg_stmt->execute()) {
        $new_id = $insert_msg_stmt->insert_id;
        // Return the new message as JSON
        header('Content-Type: application/json');
        echo json_encode([
            "sender_type" => "tenant",
            "id" => $new_id,
            "message" => $message_to_send,
            "created_at" => date("h:i A")
        ]);
    } else {
        http_response_code(500);
        die(json_encode(["error" => "Failed to send message."]));
    }
} else {
    http_response_code(500);
    die(json_encode(["error" => "Could not find admin."]));
}