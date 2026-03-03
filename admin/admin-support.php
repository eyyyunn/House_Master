<?php
session_start();
include __DIR__ . "/../config.php";


if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION["admin_id"];
$boarding_code = $_SESSION['boarding_code'];


$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

// for sending messages
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['message']) && !empty(trim($_POST['message'])) && $tenant_id > 0) {
    $message = trim($_POST['message']);
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_id, message) VALUES ('admin', ?, ?, ?)");
    $stmt->bind_param("iis", $admin_id, $tenant_id, $message);
    $stmt->execute();
    
    header("Location: admin-support.php?tenant_id=" . $tenant_id);
    exit();
}


$tenants_stmt = $conn->prepare(
    "SELECT DISTINCT t.id, t.fullname 
     FROM tenants t 
     JOIN messages m ON t.id = m.sender_id OR t.id = m.receiver_id
     WHERE t.admin_id = ? AND m.sender_type = 'tenant'"
);
$tenants_stmt->bind_param("i", $admin_id);
$tenants_stmt->execute();
$tenants = $tenants_stmt->get_result();


$messages = null;
if ($tenant_id > 0) {
    $messages_stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $messages_stmt->bind_param("iiii", $tenant_id, $admin_id, $admin_id, $tenant_id);
    $messages_stmt->execute();
    $messages = $messages_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Support — HouseMaster</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="side.css">
  <link rel="stylesheet" href="main.css">
  <link rel="stylesheet" href="../tenant/tenant.css">
</head>
<body>

<?php include("navbar.php"); ?>

<div class="main p-4">
    <h3 class="fw-bold text-dark mb-3">💬 Tenant Support</h3>
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">Conversations</div>
                <div class="list-group list-group-flush">
                    <?php if ($tenants->num_rows > 0): ?>
                        <?php while($t = $tenants->fetch_assoc()): ?>
                            <a href="admin-support.php?tenant_id=<?php echo $t['id']; ?>" class="list-group-item list-group-item-action <?php echo ($t['id'] == $tenant_id) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($t['fullname']); ?>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="list-group-item">No conversations yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="chat-box mb-3">
                        <?php if ($messages): ?>
                            <?php if ($messages->num_rows > 0): ?>
                                <?php while($msg = $messages->fetch_assoc()): ?>
                                <div class="chat-message <?php echo $msg['sender_type']; ?>">
                                    <div class="chat-bubble <?php echo $msg['sender_type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">No messages in this conversation.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-center text-muted">Select a conversation to view messages.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($tenant_id > 0): ?>
                    <form method="POST" class="d-flex">
                        <input type="text" name="message" class="form-control me-2" placeholder="Type your message..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>