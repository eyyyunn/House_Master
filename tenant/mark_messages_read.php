<?php
session_start();
include __DIR__ . "/../config.php";

// Security: Ensure a tenant is logged in
if (!isset($_SESSION["tenant_id"])) {
    http_response_code(403);
    die("Access Denied");
}

$tenant_id = $_SESSION["tenant_id"];

// Mark all messages sent by an admin to this tenant as read
$update_read_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_type = 'admin' AND is_read = 0");
$update_read_stmt->bind_param("i", $tenant_id);

if ($update_read_stmt->execute()) {
    echo "success";
} else {
    http_response_code(500);
    echo "error";
}