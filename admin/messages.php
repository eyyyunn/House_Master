<?php

require_once 'header.php';

$admin_id = $_SESSION["admin_id"];
$boarding_code = $_SESSION['boarding_code'];


$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0; 
$prefilled_message = isset($_GET['message']) ? trim($_GET['message']) : '';


$tenants_stmt = $conn->prepare(
    "SELECT DISTINCT t.id, t.fullname, r.room_label
     FROM tenants t 
     LEFT JOIN tenant_rooms tr ON t.id = tr.tenant_id
     LEFT JOIN rooms r ON tr.room_id = r.id
     JOIN messages m ON (m.sender_id = t.id AND m.sender_type = 'tenant' AND m.receiver_id = ?) OR (m.receiver_id = t.id AND m.sender_type = 'admin' AND m.sender_id = ?)
     WHERE t.admin_id = ?"
);
$tenants_stmt->bind_param("iii", $admin_id, $admin_id, $admin_id);
$tenants_stmt->execute();
$tenants = $tenants_stmt->get_result();
$tenant_list = $tenants->fetch_all(MYSQLI_ASSOC); 

// Check for System messages
$sys_check = $conn->prepare("SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND sender_type = 'system'");
$sys_check->bind_param("i", $admin_id);
$sys_check->execute();
$sys_count = $sys_check->get_result()->fetch_assoc()['count'];

if ($sys_count > 0) {
    
    array_unshift($tenant_list, [
        'id' => -1, 
        'fullname' => 'System Notifications',
        'room_label' => 'Admin'
    ]);
}


$messages = null;
if ($tenant_id != 0) {
    if ($tenant_id == -1) {
        
        $messages_stmt = $conn->prepare("SELECT * FROM messages WHERE receiver_id = ? AND sender_type = 'system' ORDER BY created_at ASC");
        $messages_stmt->bind_param("i", $admin_id);
        $update_read_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_type = 'system'");
        $update_read_stmt->bind_param("i", $admin_id);
    } else {
     
    $messages_stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $messages_stmt->bind_param("iiii", $tenant_id, $admin_id, $admin_id, $tenant_id);
    $update_read_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND sender_type = 'tenant'");
    $update_read_stmt->bind_param("ii", $admin_id, $tenant_id);
    }
    
    $messages_stmt->execute();
    $messages = $messages_stmt->get_result();
    $update_read_stmt->execute();
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
  <style>
    :root {
        --primary-color: #05445E;
        --secondary-color: #189AB4;
        --accent-color: #75E6DA;
        --light-bg: #f4f6f9;
    }
    body {
        background-color: var(--light-bg);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .main {
        padding: 2rem;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .page-header {
        margin-bottom: 1.5rem;
        flex-shrink: 0;
    }
    .page-title {
        color: var(--primary-color);
        font-weight: 800;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }
    
    /* Chat Layout */
    .chat-layout {
        display: flex;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.05);
        overflow: hidden;
        flex-grow: 1;
        min-height: 0; 
        border: 1px solid #e1e5eb;
    }

    /* Sidebar */
    .chat-sidebar {
        width: 320px;
        border-right: 1px solid #f0f0f0;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid #f0f0f0;
        font-weight: 800;
        color: var(--primary-color);
    }
    .user-list {
        overflow-y: auto;
        flex-grow: 1;
    }
    .user-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f4f6f9;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: #495057;
    }
    .user-item:hover { background-color: #f8f9fa; color: var(--primary-color); }
    .user-item.active { background-color: #eef2f5; border-left: 4px solid var(--primary-color); color: var(--primary-color); }
    
    .user-avatar {
        width: 45px; height: 45px;
        border-radius: 50%;
        background-color: var(--secondary-color);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        margin-right: 15px;
        flex-shrink: 0;
        font-size: 1.1rem;
    }
    .user-info { flex-grow: 1; min-width: 0; }
    .user-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .user-meta { font-size: 0.75rem; color: #8898aa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }

    /* Chat Area */
    .chat-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }
    .chat-header {
        padding: 1rem 2rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        background: #fff;
        height: 73px; /* Match sidebar header approx height */
    }
    .chat-messages {
        flex-grow: 1;
        padding: 2rem;
        overflow-y: auto;
        background-color: #fcfcfc;
        display: flex;
        flex-direction: column;
    }
    .chat-input-area {
        padding: 1.5rem;
        background: #fff;
        border-top: 1px solid #f0f0f0;
    }
    
    /* Messages */
    .message-wrapper {
        display: flex;
        flex-direction: column;
        margin-bottom: 1rem;
        width: 100%;
    }
    .message-wrapper.me { align-items: flex-end; }
    .message-wrapper.other { align-items: flex-start; }
    
    .message-bubble {
        max-width: 70%;
        padding: 12px 20px;
        border-radius: 18px;
        position: relative;
        font-size: 0.95rem;
        line-height: 1.5;
        word-wrap: break-word;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .message-wrapper.me .message-bubble {
        background-color: var(--primary-color);
        color: #fff;
        border-bottom-right-radius: 2px;
    }
    .message-wrapper.other .message-bubble {
        background-color: #fff;
        color: #212529;
        border-bottom-left-radius: 2px;
        border: 1px solid #f0f0f0;
    }
    .message-time {
        font-size: 0.7rem;
        margin-top: 6px;
        opacity: 0.7;
        display: block;
    }
    
    /* Input */
    .chat-input {
        border-radius: 30px;
        padding: 14px 24px;
        border: 1px solid #e0e6ed;
        background: #f9f9f9;
    }
    .chat-input:focus {
        background: #fff;
        box-shadow: 0 0 0 3px rgba(5, 68, 94, 0.1);
        border-color: var(--secondary-color);
    }
    .btn-send {
        width: 50px; height: 50px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin-left: 12px;
        background: var(--primary-color);
        border: none;
        color: #fff;
        transition: transform 0.2s;
    }
    .btn-send:hover { transform: scale(1.1); background: #032f40; }
  </style>
</head>
<body>

<?php include("navbar.php"); ?>

<div class="main">
    <div class="page-header">
        <h3 class="page-title">Messages</h3>
        <p class="page-subtitle">Chat with tenants and handle support requests.</p>
    </div>

    <div class="chat-layout">
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                Conversations
            </div>
            <div class="user-list">
                <?php if (count($tenant_list) > 0): ?>
                    <?php foreach($tenant_list as $t): ?>
                        <a href="messages.php?tenant_id=<?php echo $t['id']; ?>" class="user-item <?php echo ($t['id'] == $tenant_id) ? 'active' : ''; ?>">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($t['fullname'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <span class="user-name"><?php echo htmlspecialchars($t['fullname']); ?></span>
                                <span class="user-meta">
                                    <?php echo $t['room_label'] ? htmlspecialchars($t['room_label']) : 'No Room'; ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted small">No conversations yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Content -->
        <div class="chat-content">
            <?php if ($tenant_id != 0): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <?php 
                        // Find current tenant name and room
                        $current_tenant_name = "Unknown";
                        $current_tenant_room = "";
                        foreach($tenant_list as $t) {
                            if($t['id'] == $tenant_id) {
                                $current_tenant_name = $t['fullname'];
                                $current_tenant_room = $t['room_label'];
                                break;
                            }
                        }
                    ?>
                    <div class="d-flex align-items-center">
                        <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem; margin-right: 12px;">
                            <?php echo strtoupper(substr($current_tenant_name, 0, 1)); ?>
                        </div>
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($current_tenant_name); ?></div>
                            <div class="small text-muted"><?php echo $current_tenant_room ? htmlspecialchars($current_tenant_room) : 'Chat History'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages">
                    <?php if ($messages && $messages->num_rows > 0): ?>
                        <?php while($msg = $messages->fetch_assoc()): 
                            $is_me = ($msg['sender_type'] === 'admin');
                            $wrapper_class = $is_me ? 'me' : 'other';
                        ?>
                        <div class="message-wrapper <?php echo $wrapper_class; ?>" data-message-id="<?php echo $msg['id']; ?>">
                            <div class="message-bubble">
                                <?php echo htmlspecialchars($msg['message']); ?>
                            </div>
                            <span class="message-time">
                                <?php echo date("h:i A", strtotime($msg['created_at'])); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted my-auto">
                            <i class="far fa-comments fa-3x mb-3 opacity-25"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Input Area -->
                <?php if ($tenant_id > 0): ?>
                <div class="chat-input-area">
                    <form id="admin-chat-form" class="d-flex align-items-center">
                        <input type="text" id="chat-message-input" class="form-control chat-input" placeholder="Type your message..." required autocomplete="off" value="<?php echo htmlspecialchars($prefilled_message); ?>">
                        <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                    <div class="bg-light rounded-circle p-4 mb-3">
                        <i class="far fa-comment-dots fa-4x text-secondary opacity-50"></i>
                    </div>
                    <h5>Select a conversation</h5>
                    <p class="small">Choose a tenant from the list to start chatting.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatBox = document.querySelector('.chat-messages');
        const chatForm = document.getElementById('admin-chat-form');
        
        function scrollToBottom() {
            if(chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }
        
        scrollToBottom();

        
        const tenantId = <?php echo $tenant_id; ?>;
        if (tenantId != 0) {
            startChatPolling(tenantId);
        }

        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageInput = document.getElementById('chat-message-input');
                const message = messageInput.value.trim();

                if (message === '' || tenantId === 0) {
                    return;
                }

                const formData = new FormData();
                formData.append('message', message);
                formData.append('tenant_id', tenantId);

                fetch('send_admin_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                    } else {
                       
                        messageInput.value = '';

                        
                        const messageHtml = `
                            <div class="message-wrapper me" data-message-id="${data.id}">
                                <div class="message-bubble">
                                    ${escapeHtml(data.message)}
                                </div>
                                <span class="message-time">${data.created_at}</span>
                            </div>`;
                        
                        const noMsgP = chatBox.querySelector('.text-center.text-muted');
                        if (noMsgP) noMsgP.remove();
                        
                        chatBox.insertAdjacentHTML('beforeend', messageHtml);
                        scrollToBottom();
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        }
    });

    // --- Chat Polling Logic ---
    let chatPollInterval;

    function startChatPolling(tenantId) {
        stopChatPolling(); 
        chatPollInterval = setInterval(() => fetchNewMessages(tenantId), 3000);
    }

    function stopChatPolling() {
        clearInterval(chatPollInterval);
    }

    function fetchNewMessages(tenantId) {
        const chatBox = document.querySelector('.chat-messages');
        if (!chatBox) return;

        const lastMessage = chatBox.querySelector('.message-wrapper:last-child');
        const lastId = lastMessage ? lastMessage.getAttribute('data-message-id') : 0;

       
        fetch(`../get_new_messages.php?tenant_id=${tenantId}&last_id=${lastId}`)
            .then(response => response.json())
            .then(messages => {
                if (messages.length > 0) {
                    messages.forEach(msg => {
                       
                        if (msg.sender_type === 'tenant' || msg.sender_type === 'system') {
                            const messageHtml = `
                                <div class="message-wrapper other" data-message-id="${msg.id}">
                                    <div class="message-bubble">
                                        ${escapeHtml(msg.message)}
                                    </div>
                                    <span class="message-time">${msg.created_at_formatted}</span>
                                </div>`;
                            
                            const noMsgP = chatBox.querySelector('.text-center.text-muted');
                            if (noMsgP) noMsgP.remove();

                            chatBox.insertAdjacentHTML('beforeend', messageHtml);
                        }
                    });
                    chatBox.scrollTop = chatBox.scrollHeight;

                   
                    if (window.housemaster_last_update) {
                        window.housemaster_last_update = messages[messages.length - 1].created_at_timestamp;
                    }
                }
            });
    }

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>
</body>
</html>
