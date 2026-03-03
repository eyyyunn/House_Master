<?php
session_start();
include __DIR__ . "/../config.php";

// Require admin login
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['tenant_id'], $_POST['message'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $message = trim($_POST['message']);
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'payments.php';

    if (empty($message) || $tenant_id === 0) {
        // Handle error - maybe set a session flash message
        header("Location: " . $redirect_url);
        exit();
    }

    // Security check: Verify the admin owns this tenant
    $verify_stmt = $conn->prepare("SELECT id FROM tenants WHERE id = ? AND admin_id = ?");
    $verify_stmt->bind_param("ii", $tenant_id, $admin_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        // Insert the message into the database
        $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_id, message) VALUES ('admin', ?, ?, ?)");
        $stmt->bind_param("iis", $admin_id, $tenant_id, $message);
        $stmt->execute();
    }

    // Redirect back to the original page (e.g., payments.php with filters)
    header("Location: " . $redirect_url);
    exit();
} else {
    // Redirect if accessed directly or without proper POST data
    header("Location: dashboard.php");
    exit();
}