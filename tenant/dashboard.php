<?php
session_start();
include __DIR__ . "/../config.php";  // config is outside /tenant folder

//  Require tenant login
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-auth.php");
    exit();
}

$tenant_id = $_SESSION["tenant_id"];

//  Fetch tenant info
$tenant_stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
$tenant_stmt->bind_param("i", $tenant_id);
$tenant_stmt->execute();
$tenant = $tenant_stmt->get_result()->fetch_assoc();

// ✅ Self-heal: Fix missing boarding code if admin_id is present
if (empty($tenant['boarding_code']) && !empty($tenant['admin_id'])) {
    $ac_stmt = $conn->prepare("SELECT boarding_code FROM admins WHERE id = ?");
    $ac_stmt->bind_param("i", $tenant['admin_id']);
    $ac_stmt->execute();
    $ac_res = $ac_stmt->get_result()->fetch_assoc();
    if ($ac_res) {
        $tenant['boarding_code'] = $ac_res['boarding_code'];
        $conn->query("UPDATE tenants SET boarding_code = '{$ac_res['boarding_code']}' WHERE id = $tenant_id");
    }
}

//  If tenant is inactive, log them out
if ($tenant['status'] === 'inactive') {
    header("Location: ../tenant-logout.php?reason=inactive");
    exit();
}

//  Fetch room assignment (if exists)
$room = $conn->query("
    SELECT r.* FROM rooms r
    INNER JOIN tenant_rooms tr ON r.id = tr.room_id
    WHERE tr.tenant_id = $tenant_id
")->fetch_assoc();

//  Next PENDING payment
$payment_stmt = $conn->prepare("
    SELECT * FROM payments
    WHERE tenant_id = ? AND status = 'pending'
    ORDER BY due_date ASC LIMIT 1");
$payment_stmt->bind_param("i", $tenant_id);
$payment_stmt->execute();
$payment = $payment_stmt->get_result()->fetch_assoc();

// Calculate remaining days
$days_remaining = null;
$stay_until = null;
$today = new DateTime();
$today->setTime(0, 0, 0);

// Calculate based on last PAID bill only
$last_paid_stmt = $conn->prepare("SELECT due_date FROM payments WHERE tenant_id = ? AND status = 'paid' ORDER BY due_date DESC LIMIT 1");
$last_paid_stmt->bind_param("i", $tenant_id);
$last_paid_stmt->execute();
$last_paid = $last_paid_stmt->get_result()->fetch_assoc();

if ($last_paid) {
    $last_due = new DateTime($last_paid['due_date']);
    $last_due->setTime(0, 0, 0);
    $days_remaining = (int)$today->diff($last_due)->format('%r%a');
    $stay_until = $last_due->format('M d, Y');
} elseif ($payment) {
    // Fallback: Use next pending payment if no paid history
    $pending_due = new DateTime($payment['due_date']);
    $pending_due->setTime(0, 0, 0);
    $days_remaining = (int)$today->diff($pending_due)->format('%r%a');
    $stay_until = $pending_due->format('M d, Y');
}

//  Recent notices (last 3)
$latest_notice = null;
$boarding_code = $tenant['boarding_code'];
$notices_stmt = $conn->prepare("SELECT * FROM notices WHERE boarding_code = ? ORDER BY created_at DESC LIMIT 1");
$notices_stmt->bind_param("s", $boarding_code);
$notices_stmt->execute();
$latest_notice = $notices_stmt->get_result()->fetch_assoc();

//  Fetch all messages for the chat modal
$admin_id = $tenant['admin_id'];
$messages_stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
$messages_stmt->bind_param("iiii", $tenant_id, $admin_id, $admin_id, $tenant_id);
$messages_stmt->execute();
$recent_messages = $messages_stmt->get_result();


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Tenant Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/adminlte.min.css">
  <link rel="stylesheet" href="tenant.css">
  <style>
    body {
        background-color: #f4f6f9;
    }
     
    .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 15px;
    }
    .modal-dialog-scrollable .modal-body {
    overflow-y: hidden;
    }
    .welcome-banner {
        background-color: #fff;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 2px 20px rgba(0,0,0,0.03);
        border-left: 5px solid #05445E;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .welcome-text h2 {
        color: #05445E;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .welcome-text p {
        color: #6c757d;
        margin-bottom: 0;
    }
    .boarding-code-badge {
        background-color: #e3f2fd;
        color: #05445E;
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        border: 1px solid rgba(24, 154, 180, 0.2);
    }
    .info-card {
        background: #fff;
        border-radius: 16px;
        padding: 25px;
        height: 100%;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.03);
    }
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    .card-icon-bg {
        
        font-size: 2rem;
        
        color: #05445E;
        
    }
    .fa-bullhorn {
    content: "\f0a1";
    color: #ffffff;

}
    .horn {
      
      color: #05445E !important;
     
    }
    .card-label {
        color: #adb5bd;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }
    .card-value {
        color: #2c3e50;
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 5px;
    }
    .card-subtext {
        font-size: 0.9rem;
        color: #6c757d;
    }
   
    .status-badge {
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .border {
      border-radius: 8px !important;
      border-color: #05445E !important;
      color: #05445E;
    }
    .status-paid { background-color: #d1e7dd; color: #0f5132; }
    .status-pending { background-color: #fff3cd; color: #664d03; }
    .status-overdue { background-color: #f8d7da; color: #842029; }
    
    .announcement-section {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    }
    .section-title {
        color: #05445E;
        font-weight: 700;
        margin-bottom: 0;
    }
    .notice-item {
        background-color: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        border-left: 5px solid #189AB4;
    }
    .notice-date {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 8px;
        display: block;
    }
    .notice-title {
        font-weight: 700;
        color: #212529;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    .btn-action {
        background-color: #05445E;
        color: white;
        border-radius: 10px;
        padding: 10px 20px;
        border: none;
        transition: background 0.3s;
        font-weight: 600;
    }
    .btn-action:hover {
        background-color: #032f40;
        color: white;
    }
  </style>
</head>
<body>

<?php include("navbar.php"); ?>

<!-- Dashboard Content --> 
<div class="main-container">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h2>Hello, <?php echo htmlspecialchars($tenant["fullname"]); ?>!</h2>
            <p>Welcome back to your dashboard. Here's what's happening with your stay.</p>
        </div>
        <?php if (!empty($tenant['boarding_code'])): ?>
        <div class="boarding-code-badge">
            <i class="fas fa-home me-2"></i> Code: <?php echo htmlspecialchars($tenant['boarding_code']); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Room Card -->
        <div class="col-md-4">
            <div class="info-card">
                <i class="fas fa-door-open card-icon-bg"></i>
                <div class="card-label">My Room</div>
                <?php if ($room): ?>
                    <div class="card-value"><?php echo htmlspecialchars($room["room_label"]); ?></div>
                    <div class="card-subtext mb-2">
                        <i class="fas fa-users me-1"></i> Capacity: <?php echo $room["capacity"]; ?>
                    </div>
                    <div class="card-subtext">
                        Rent: <span class="text-primary fw-bold">₱<?php echo number_format($room["rental_rate"]); ?></span> / month
                    </div>
                <?php else: ?>
                    <div class="card-value text-muted">Not Assigned</div>
                    <div class="card-subtext">Please contact admin</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Card -->
        <div class="col-md-4">
            <div class="info-card">
                <i class="fas fa-file-invoice-dollar card-icon-bg"></i>
                <div class="card-label">Next Payment</div>
                <?php if ($payment): ?>
                    <div class="card-value">₱<?php echo number_format($payment["amount"]); ?></div>
                    <?php
                        $payment_status = $payment['status'];
                        $badge_class = 'status-pending';
                        $icon_class = 'clock';
                        $status_text = 'Pending';

                        if ($payment_status == 'pending' && strtotime($payment['due_date']) < time()) {
                            $badge_class = 'status-overdue';
                            $icon_class = 'exclamation-circle';
                            $status_text = 'Overdue';
                        }
                    ?>
                    <div class="mb-3">
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <i class="fas fa-<?php echo $icon_class; ?> me-1"></i> <?php echo $status_text; ?>
                        </span>
                    </div>
                    <div class="card-subtext">
                        Due Date: <strong><?php echo date("M d, Y", strtotime($payment["due_date"])); ?></strong>
                    </div>
                    <?php if ($days_remaining !== null): ?>
                        <div class="mt-2 small fw-bold <?php echo $days_remaining < 0 ? 'text-danger' : 'text-primary'; ?>">
                            <?php echo $days_remaining < 0 ? abs($days_remaining) . " Days Overdue" : $days_remaining . " Days Remaining"; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card-value text-success">Paid</div>
                    <div class="card-subtext">No pending bills</div>
                    <?php if ($days_remaining !== null): ?>
                        <div class="mt-2 small text-muted">
                            Paid until: <strong><?php echo $stay_until; ?></strong>
                            <br>
                            <span class="text-primary fw-bold"><?php echo $days_remaining; ?> Days Remaining</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Support Card -->
        <div class="col-md-4">
            <div class="info-card">
                <i class="fas fa-comments card-icon-bg"></i>
                <div class="card-label">Support</div>
                <div class="card-value">
                    <?php echo ($unread_messages > 0) ? $unread_messages : '0'; ?>
                </div>
                <div class="card-subtext mb-4">Unread Messages</div>
                <button class="btn btn-action w-100" data-bs-toggle="modal" data-bs-target="#supportModal">
                    <i class="fas fa-paper-plane me-2"></i> Open Chat
                </button>
            </div>
        </div>
    </div>

    <!-- Announcement Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="announcement-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title"><i class="fas fa-bullhorn horn text-primary me-2"></i> Latest Announcement</h4>
                    <a href="tenant-announcement.php" class="text-decoration-none text-muted small fw-bold">View All <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                
                <?php if ($latest_notice): ?>
                    <div class="notice-item">
                        <span class="notice-date"><i class="far fa-calendar-alt me-1"></i> <?php echo date("F j, Y", strtotime($latest_notice["created_at"])); ?></span>
                        <div class="notice-title"><?php echo htmlspecialchars($latest_notice["title"]); ?></div>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($latest_notice['body'], 0, 250)) . (strlen($latest_notice['body']) > 250 ? '...' : ''); ?></p>
                        <button class="btn border btn-sm btn-outline-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#noticeModalTenant<?php echo $latest_notice['id']; ?>">Read Full Notice</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                        <p>No new announcements at this time.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Support Chat Modal -->
<div class="modal fade" id="supportModal" tabindex="-1" aria-labelledby="supportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header border-bottom-0" style="background-color: #05445E; color: white; padding: 1.5rem;">
        <div class="d-flex align-items-center">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; font-size: 1.2rem;">
                <i class="fas fa-user-shield" style="color: #05445E;"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold mb-0" id="supportModalLabel">Admin Support</h5>
                <small class="opacity-75">We usually reply within a few hours</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="background-color: #f4f6f9;">
        <div class="chat-box">
          <?php if ($recent_messages->num_rows > 0): ?>
            <?php while($msg = $recent_messages->fetch_assoc()): ?>
              <div class="chat-message <?php echo htmlspecialchars($msg['sender_type']); ?>" data-message-id="<?php echo $msg['id']; ?>">
                <div class="chat-bubble <?php echo htmlspecialchars($msg['sender_type']); ?>">
                    <?php echo htmlspecialchars($msg['message']); ?>
                </div>
                <span class="chat-time"><?php echo date("h:i A", strtotime($msg['created_at'])); ?></span>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                <i class="far fa-comments fa-3x mb-3 opacity-25"></i>
                <p>No messages yet. Start the conversation!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer p-0 border-top-0">
        <form id="support-chat-form" class="d-flex w-100 align-items-center p-3 bg-white">
          <input type="text" id="chat-message-input" class="form-control chat-input shadow-none" placeholder="Type your message..." required autocomplete="off">
          <button type="submit" class="btn-send shadow-sm"><i class="fas fa-paper-plane"></i></button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Announcement Modals -->
<?php
  // We only need a modal for the single latest notice now
  if ($latest_notice):
?>
<div class="modal fade" id="noticeModalTenant<?php echo $latest_notice['id']; ?>" tabindex="-1" aria-labelledby="noticeModalLabelTenant<?php echo $latest_notice['id']; ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="noticeModalLabelTenant<?php echo $latest_notice['id']; ?>"><?php echo htmlspecialchars($latest_notice['title']); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><small class="text-muted">Posted on: <?php echo date("F j, Y, g:i a", strtotime($latest_notice['created_at'])); ?></small></p>
        <hr>
        <p><?php echo nl2br(htmlspecialchars($latest_notice['body'])); ?></p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<footer class="text-center mt-5 mb-3 text-muted">
  HouseMaster © 2025 — Boarding House & Dormitory Management System
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pass the server's last update timestamp to the JavaScript poller.
    <?php
        $ts_stmt = $conn->query("SELECT state_value FROM system_state WHERE state_key = 'last_update_timestamp'");
        $current_ts = $ts_stmt->fetch_assoc()['state_value'];
    ?>
    window.housemaster_last_update = <?php echo $current_ts ?: 0; ?>;
</script>
<script src="../assets/js/autoupdate.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const supportModal = document.getElementById('supportModal');
        const chatForm = document.getElementById('support-chat-form');
        const chatBox = supportModal.querySelector('.chat-box');

        // Function to scroll to the bottom of the chat
        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // When the modal is shown, scroll to the bottom
        supportModal.addEventListener('shown.bs.modal', function () {
            startChatPolling();
            scrollToBottom();
            
            // Mark messages as read via AJAX
            fetch('mark_messages_read.php', { method: 'POST' })
                .then(response => {
                    if (response.ok) {
                        // Optionally, update the UI to remove the unread badge without a page reload
                        const unreadBadge = document.querySelector('.position-absolute.top-0.start-100.translate-middle.badge');
                        const unreadText = document.getElementById('unread-message-text');
                        
                        if (unreadText) unreadText.textContent = 'You have 0 unread message(s).';
                        if (unreadBadge) unreadBadge.remove();
                    }
                });
        });

        // When the modal is hidden, stop polling
        supportModal.addEventListener('hidden.bs.modal', function () {
            stopChatPolling();
        });


        // Handle form submission with AJAX
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('chat-message-input');
            const message = messageInput.value.trim();

            if (message === '') {
                return;
            }

            const formData = new FormData();
            formData.append('message', message);

            fetch('send_tenant_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    // Clear input
                    messageInput.value = '';

                    // Append new message to chat box
                    const messageHtml = `
                        <div class="chat-message tenant" data-message-id="${data.id}">
                            <div class="chat-bubble tenant">
                                ${escapeHtml(data.message)}
                            </div>
                            <span class="chat-time">${data.created_at}</span>
                        </div>`;
                    chatBox.insertAdjacentHTML('beforeend', messageHtml);
                    scrollToBottom();
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // --- Chat Polling Logic ---
    let chatPollInterval;

    function startChatPolling() {
        // Clear any existing interval
        stopChatPolling(); 

        // Start polling immediately, then every 3 seconds
        fetchNewMessages();
        chatPollInterval = setInterval(fetchNewMessages, 3000);
    }

    function stopChatPolling() {
        clearInterval(chatPollInterval);
    }

    function fetchNewMessages() {
        const chatBox = document.querySelector('#supportModal .chat-box');
        const lastMessage = chatBox.querySelector('.chat-message:last-child');
        const lastId = lastMessage ? lastMessage.getAttribute('data-message-id') : 0;

        // Note: We use ../get_new_messages.php because this file is in the /tenant/ directory
        fetch(`../get_new_messages.php?last_id=${lastId}`)
            .then(response => response.json())
            .then(messages => {
                if (messages.length > 0) {
                    messages.forEach(msg => {
                        // Only append messages from the other user (admin)
                        if (msg.sender_type === 'admin') {
                            const messageHtml = `
                                <div class="chat-message ${msg.sender_type}" data-message-id="${msg.id}">
                                    <div class="chat-bubble ${msg.sender_type}">
                                        ${msg.message}
                                    </div>
                                    <span class="chat-time">${msg.created_at_formatted}</span>
                                </div>`;
                            chatBox.insertAdjacentHTML('beforeend', messageHtml);
                        }
                    });
                    chatBox.scrollTop = chatBox.scrollHeight; // Scroll to new message
                }
                 // If we received new messages, update the global timestamp
                 // to prevent the main page poller from doing a full reload.
                 if (messages.length > 0) {
                    window.housemaster_last_update = messages[messages.length - 1].created_at_timestamp;
                 }
            });
    }
</script>
</body>
</html>
