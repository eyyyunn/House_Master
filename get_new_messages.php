<?php
session_start();
include __DIR__ . "/config.php";

$user_type = null;
$user_id = null;
$other_user_id = null;

if (isset($_SESSION["admin_id"])) {
    $user_type = 'admin';
    $user_id = $_SESSION["admin_id"];
    // For admin, the other user (tenant) must be specified
    $other_user_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;
} elseif (isset($_SESSION["tenant_id"])) {
    $user_type = 'tenant';
    $user_id = $_SESSION["tenant_id"];
    // For tenant, the other user is their admin
    $stmt = $conn->prepare("SELECT admin_id FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $other_user_id = $result->fetch_assoc()['admin_id'];
    }
}

if (!$user_id || !$other_user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication or target user invalid.']);
    exit();
}

$last_message_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$sql = "
    SELECT id, sender_type, message, created_at
    FROM messages
    WHERE id > ?
      AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ORDER BY created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $last_message_id, $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$new_messages = [];
while ($row = $result->fetch_assoc()) {
    $row['created_at_formatted'] = date("h:i A", strtotime($row['created_at']));
    $row['created_at_timestamp'] = strtotime($row['created_at']);
    $new_messages[] = $row;
}

header('Content-Type: application/json');
echo json_encode($new_messages);
exit();