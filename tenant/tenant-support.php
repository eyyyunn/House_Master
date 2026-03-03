<?php
session_start();
include __DIR__ . "/../config.php";

// Redirect if not logged in
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-auth.php");
    exit();
}

$tenant_id = $_SESSION["tenant_id"];

// Get the tenant's admin ID
$admin_id_stmt = $conn->prepare("SELECT admin_id FROM tenants WHERE id = ?");
$admin_id_stmt->bind_param("i", $tenant_id);
$admin_id_stmt->execute();
$admin_result = $admin_id_stmt->get_result();
$tenant_data = $admin_result->fetch_assoc();
$admin_id = $tenant_data['admin_id'];

// Mark messages from admin as read
$update_read_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_type = 'admin'");
$update_read_stmt->bind_param("i", $tenant_id);
$update_read_stmt->execute();
?>
<?php
// Redirect to the dashboard where the chat modal now lives.
// The 'open_chat' parameter can be used by JS to automatically open the modal.
header("Location: dashboard.php?open_chat=true");
exit();
