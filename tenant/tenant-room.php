<?php
session_start();
include __DIR__ . "/../config.php";

// ✅ Require tenant login
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-auth.php");
    exit();
}

$tenant_id = $_SESSION["tenant_id"];

// ✅ Fetch tenant info to get admin_id
$tenant_stmt = $conn->prepare("SELECT admin_id, boarding_code FROM tenants WHERE id = ?");
$tenant_stmt->bind_param("i", $tenant_id);
$tenant_stmt->execute();
$tenant = $tenant_stmt->get_result()->fetch_assoc();
$admin_id = $tenant['admin_id'] ?? null;

// ✅ Self-heal: Fix missing boarding code if admin_id is present
if (empty($tenant['boarding_code']) && $admin_id) {
    $ac_stmt = $conn->prepare("SELECT boarding_code FROM admins WHERE id = ?");
    $ac_stmt->bind_param("i", $admin_id);
    $ac_stmt->execute();
    $ac_res = $ac_stmt->get_result()->fetch_assoc();
    if ($ac_res) {
        $tenant['boarding_code'] = $ac_res['boarding_code'];
        $conn->query("UPDATE tenants SET boarding_code = '{$ac_res['boarding_code']}' WHERE id = $tenant_id");
    }
}

// ✅ Fetch tenant’s room assignment
$sql = "
    SELECT r.id AS room_id, r.room_label, r.capacity, r.rental_rate, t.start_boarding_date
    FROM tenant_rooms tr
    INNER JOIN rooms r ON tr.room_id = r.id
    INNER JOIN tenants t ON tr.tenant_id = t.id
    WHERE tr.tenant_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

// ✅ Fetch room images
$room_images = [];
if ($room) {
    $images_stmt = $conn->prepare("SELECT image_filename FROM room_images WHERE room_id = ? ORDER BY created_at ASC");
    $images_stmt->bind_param("i", $room['room_id']);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    while ($img_row = $images_result->fetch_assoc()) {
        $room_images[] = $img_row['image_filename'];
    }
}

// ✅ Calculate duration of stay
$duration = null;
if ($room && $room['start_boarding_date']) {
    $assigned_date = new DateTime($room['start_boarding_date']);
    $now = new DateTime();
    $interval = $assigned_date->diff($now);
    $duration = $interval->y > 0 
        ? $interval->y . " year(s)" 
        : ($interval->m > 0 ? $interval->m . " month(s)" : $interval->d . " day(s)");
}

// ✅ Fetch next pending payment to calculate days remaining
$payment = null;
$days_remaining = null;
$stay_until = null;
$today = new DateTime();
$today->setTime(0, 0, 0);

if ($room) {
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
    } else {
        // Fallback: Check for next pending payment if no paid history
        $pending_stmt = $conn->prepare("SELECT due_date FROM payments WHERE tenant_id = ? AND status = 'pending' ORDER BY due_date ASC LIMIT 1");
        $pending_stmt->bind_param("i", $tenant_id);
        $pending_stmt->execute();
        $pending = $pending_stmt->get_result()->fetch_assoc();
        
        if ($pending) {
            $pending_due = new DateTime($pending['due_date']);
            $pending_due->setTime(0, 0, 0);
            $days_remaining = (int)$today->diff($pending_due)->format('%r%a');
            $stay_until = $pending_due->format('M d, Y');
        }
    }
}
// ✅ Fetch room items and rules if room exists
$items = [];
$rules = [];
if ($room) {
    // Fetch items
    $item_query = $conn->prepare("SELECT item_name, quantity, `condition` FROM room_items WHERE room_id = ? ORDER BY created_at ASC");
    $item_query->bind_param("i", $room['room_id']);
    $item_query->execute();
    $items = $item_query->get_result();

    // Fetch rules
    $rule_query = $conn->prepare("SELECT rule_text FROM room_rules WHERE room_id = ? AND type = 'rule' ORDER BY created_at ASC");
    $rule_query->bind_param("i", $room['room_id']);
    $rule_query->execute();
    $rules = $rule_query->get_result();

    // ✅ Fetch all messages for the chat modal
    $messages_stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $messages_stmt->bind_param("iiii", $tenant_id, $admin_id, $admin_id, $tenant_id);
    $messages_stmt->execute();
    $recent_messages = $messages_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Room — HouseMaster</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/adminlte.min.css">
  <link rel="stylesheet" href="tenant.css">
  <style>
    body { background-color: #f4f6f9; }
    .page-title { color: #05445E; font-weight: 800; margin-bottom: 1.5rem; }
    
    .room-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        background: #fff;
        margin-bottom: 24px;
        transition: transform 0.2s;
        overflow: hidden;
    }
    .text-warning {
    color: #05445E !important;
  }
    .room-card:hover { transform: translateY(-2px); }
    
    .room-card-header {
        background: #fff;
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        font-weight: 700;
        color: #05445E;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }
    
    .info-table { width: 100%; margin-bottom: 0; }
    .info-table tr { border-bottom: 1px solid #f0f0f0; }
    .info-table tr:last-child { border-bottom: none; }
    .info-table th {
        padding: 16px 25px;
        color: #8898aa;
        font-weight: 600;
        font-size: 0.9rem;
        width: 40%;
        vertical-align: middle;
    }
    .bg-info {
      background-color: #05445E !important;
    }
    .modal-dialog-scrollable .modal-body {
    overflow-y: hidden !important;
}
    .info-table td {
        padding: 16px 25px;
        color: #344767;
        font-weight: 600;
        text-align: right;
        vertical-align: middle;
    }

    .custom-list-group { padding: 0; list-style: none; margin: 0; }
    .custom-list-item {
        padding: 12px 15px;
        margin-bottom: 10px;
        background-color: #f8f9fa;
        border-radius: 10px;
        font-size: 0.9rem;
        color: #495057;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .custom-list-item.inclusion { border-left: 4px solid #189AB4; }
    .custom-list-item.rule { border-left: 4px solid #ffc107; background-color: #fffbf0; }
    
    .maintenance-box {
        background: #FFF;
        color: #05445E;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        box-shadow: 0 10px 20px rgba(5, 68, 94, 0.2);
    }
    .btn-chat {
        background: #05445E;
        border: 1px solid #05445E;
        color: white;
        border-radius: 10px;
        padding: 10px 25px;
        transition: all 0.3s;
        font-weight: 600;
        margin-top: 15px;
        display: inline-block;
        width: 100%;
    }
    .btn-chat:hover {
        background: white;
        color: #05445E;
        border-color: #05445E;
    }
    /* Gallery Styles */
    .image-collage {
        display: grid;
        gap: 8px;
        height: 400px;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 0;
    }
    .collage-item {
        position: relative;
        overflow: hidden;
        cursor: pointer;
        height: 100%;
    }
    .collage-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    .collage-item:hover img {
        transform: scale(1.03);
    }
    
    /* Layouts */
    .layout-1 { grid-template-columns: 1fr; }
    .layout-2 { grid-template-columns: 1fr 1fr; }
    .layout-3 { 
        grid-template-columns: 2fr 1fr; 
        grid-template-rows: 1fr 1fr;
    }
    .layout-3 .collage-item:first-child { grid-row: span 2; }
    
    .layout-4 {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
    }
    
    .layout-5 {
        grid-template-columns: 2fr 1fr 1fr;
        grid-template-rows: 1fr 1fr;
    }
    .layout-5 .collage-item:first-child {
        grid-column: 1 / 2;
        grid-row: 1 / 3;
    }
    
    .more-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.5);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        backdrop-filter: blur(2px);
        transition: background 0.3s;
    }
    .collage-item:hover .more-overlay {
        background: rgba(0,0,0,0.4);
    }

    @media (max-width: 768px) {
        .image-collage {
            height: 250px;
            grid-template-columns: 1fr !important;
            grid-template-rows: 1fr !important;
        }
        .collage-item:not(:first-child) {
            display: none;
        }
        .mobile-view-btn {
            display: block !important;
        }
    }
    .mobile-view-btn {
        display: none;
        position: absolute;
        bottom: 15px;
        right: 15px;
        background: white;
        color: black;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        pointer-events: none;
    }
    /* Modal Gallery */
    .modal-gallery .modal-content {
        border-radius: 16px;
        border: none;
        border-bottom-left-radius: 16px;
        border-bottom-right-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .modal-gallery .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f0f0f0;
    }
    .modal-gallery .modal-title {
        color: #05445E;
        font-weight: 700;
    }
    .modal-gallery .modal-body {
        padding: 1.5rem;
        background-color: #f8f9fa;
    }
    .carousel-item {
        text-align: center; /* Center the image */
    }
    .carousel-item img {
        max-height: 70vh; /* Control max height */
        object-fit: contain;
        width: auto;
        max-width: 100%;
        border-radius: 12px;
    }
    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 50%;
        padding: 1.2rem;
        background-size: 50% 50%;
    }
    .carousel-indicators {
        bottom: -10px;
    }
    .carousel-indicators button {
        background-color: #adb5bd;
    }
    .carousel-indicators .active {
        background-color: #05445E;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <?php include("navbar.php"); ?>

  <!-- Main Content -->
  <div class="container my-4">
    <h3 class="page-title text-center">My Room Details</h3>

    <div class="row g-4">
      <!-- Room Information -->
      <div class="col-lg-8">
        <div class="room-card">
          <?php if (!empty($room_images)): ?>
            <?php
                $count = count($room_images);
                $layoutClass = 'layout-1';
                if ($count == 2) $layoutClass = 'layout-2';
                if ($count == 3) $layoutClass = 'layout-3';
                if ($count == 4) $layoutClass = 'layout-4';
                if ($count >= 5) $layoutClass = 'layout-5';
                $displayImages = array_slice($room_images, 0, 5);
            ?>
            <div class="image-collage <?php echo $layoutClass; ?>">
                <?php foreach ($displayImages as $index => $image): ?>
                    <div class="collage-item" onclick="goToSlide(<?php echo $index; ?>)" data-bs-toggle="modal" data-bs-target="#imageGalleryModal">
                        <img src="../assets/uploads/rooms/<?php echo htmlspecialchars($image); ?>" alt="Room Image">
                        <?php if ($index === 4 && $count > 5): ?>
                            <div class="more-overlay">+<?php echo $count - 5; ?> Photos</div>
                        <?php endif; ?>
                        <?php if ($index === 0): ?>
                             <div class="mobile-view-btn"><i class="fas fa-th me-1"></i> All Photos</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="room-card-header"><i class="fas fa-info-circle me-2"></i> Room Information</div>
          <div class="p-0">
            <table class="info-table">
              <tbody>
                <?php if ($room): ?>
                  <tr>
                    <th scope="row"><i class="fas fa-home me-2 text-muted"></i> Boarding House Code</th>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($tenant['boarding_code']); ?></span></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-door-closed me-2 text-muted"></i> Room Label</th>
                    <td><?php echo htmlspecialchars($room['room_label']); ?></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-users me-2 text-muted"></i> Capacity</th>
                    <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-tag me-2 text-muted"></i> Monthly Rent</th>
                    <td><span class="text-primary">₱<?php echo number_format($room['rental_rate'], 2); ?></span> <small class="text-muted">/ Month</small></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-calendar-check me-2 text-muted"></i> Move-in Date</th>
                    <td><?php echo date("M d, Y", strtotime($room['start_boarding_date'])); ?></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-hourglass-half me-2 text-muted"></i> Duration of Stay</th>
                    <td><?php echo $duration ?: "N/A"; ?></td>
                  </tr>
                  <tr>
                    <th scope="row"><i class="fas fa-clock me-2 text-muted"></i> Remaining Stay</th>
                    <td>
                      <?php
                        if ($days_remaining !== null) {
                            if ($days_remaining < 0) {
                                echo '<span class="badge bg-danger">Overdue by ' . abs($days_remaining) . ' days</span>';
                            } elseif ($days_remaining <= 7) {
                                echo '<span class="badge bg-warning">' . $days_remaining . ' days left</span>';
                            } else {
                                echo '<span class="badge bg-info">' . $days_remaining . ' days left</span>';
                            }
                            if ($stay_until) {
                                echo '<div class="small text-muted mt-1">Until ' . $stay_until . '</div>';
                            }
                        } else {
                            echo '<span class="text-muted">N/A</span>';
                        }
                      ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <tr>
                    <td colspan="2" class="text-center text-muted">No room assigned yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

        <!-- Right Column -->
      <div class="col-lg-4">
      <!-- Room Inclusions -->
      <div class="room-card">
        <div class="room-card-header"><i class="fas fa-couch me-2"></i> Room Inclusions</div>
        <div class="p-3">
          <ul class="custom-list-group">
            <?php if ($room && $items->num_rows > 0): ?>
              <?php while ($item = $items->fetch_assoc()): ?>
                <li class="custom-list-item inclusion">
                  <span class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></span>
                  <div>
                    <span class="badge bg-white text-dark border shadow-sm me-1">x<?php echo htmlspecialchars($item['quantity']); ?></span>
                    <small class="text-muted"><?php echo htmlspecialchars($item['condition']); ?></small>
                  </div>
                </li>
              <?php endwhile; ?>
            <?php else: ?>
              <li class="text-muted text-center py-2">No specific items listed.</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- House Rules -->
      <div class="room-card">
        <div class="room-card-header text-warning"><i class="fas fa-exclamation-triangle me-2"></i> House Rules</div>
        <div class="p-3">
          <ul class="custom-list-group">
            <?php if ($room && $rules->num_rows > 0): ?>
              <?php while ($rule = $rules->fetch_assoc()): ?>
                <li class="custom-list-item rule"><?php echo htmlspecialchars(trim($rule['rule_text'])); ?></li>
              <?php endwhile; ?>
            <?php elseif ($room): ?>
              <li class="text-muted text-center py-2">No rules set for this room.</li>
            <?php else: ?>
              <li class="text-muted text-center py-2">No room assigned.</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- Maintenance -->
      <div class="maintenance-box mt-4">
          <h5 class="fw-bold mb-2"><i class="fas fa-tools me-2"></i>Maintenance</h5>
          <p class="mb-0 small opacity-75">Have an issue with your room? Chat with your landlord directly.</p>
          <button class="btn btn-chat" data-bs-toggle="modal" data-bs-target="#supportModal">
            <i class="fas fa-comments me-2"></i> Chat with Admin
          </button>
      </div>
      </div>

    </div>
  </div>

  <!-- Image Gallery Modal -->
  <?php if (!empty($room_images)): ?>
  <div class="modal fade modal-gallery" id="imageGalleryModal" tabindex="-1" aria-labelledby="imageGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="imageGalleryModalLabel"><?php echo htmlspecialchars($room['room_label']); ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="roomImageCarousel" class="carousel slide" data-bs-ride="carousel">
            <?php if (count($room_images) > 1): ?>
            <div class="carousel-indicators mb-0">
              <?php foreach ($room_images as $index => $image): ?>
                <button type="button" data-bs-target="#roomImageCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="carousel-inner">
              <?php foreach ($room_images as $index => $image): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                  <img src="../assets/uploads/rooms/<?php echo htmlspecialchars($image); ?>" alt="Room Image <?php echo $index + 1; ?>">
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (count($room_images) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#roomImageCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#roomImageCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
            <?php if ($room && $recent_messages->num_rows > 0): ?>
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
          <form id="support-chat-form-room" class="d-flex w-100 align-items-center p-3 bg-white">
            <input type="text" id="chat-message-input-room" class="form-control chat-input shadow-none" placeholder="Type your message..." required autocomplete="off">
            <button type="submit" class="btn-send shadow-sm"><i class="fas fa-paper-plane"></i></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <footer class="text-center mt-5 mb-3 text-muted">
    HouseMaster © 2025 — Boarding House & Dormitory Management System
  </footer>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Custom polling for auto-refresh -->
  <script>
    // Pass the server's last update timestamp to the JavaScript poller.
    <?php
        $ts_stmt = $conn->query("SELECT state_value FROM system_state WHERE state_key = 'last_update_timestamp'");
        $current_ts = $ts_stmt->fetch_assoc()['state_value'];
    ?>
    window.housemaster_last_update = <?php echo $current_ts ?: 0; ?>;
  </script>
  <script src="../admin/assets/js/autoupdate.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
        const supportModal = document.getElementById('supportModal');
        const chatForm = document.getElementById('support-chat-form-room');
        const chatBox = supportModal.querySelector('.chat-box');

        // Function to scroll to the bottom of the chat
        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // When the modal is shown, scroll to the bottom
        supportModal.addEventListener('shown.bs.modal', function () {
            startChatPolling();
            scrollToBottom();
        });

        // When the modal is hidden, stop polling
        supportModal.addEventListener('hidden.bs.modal', function () {
            stopChatPolling();
        });

        // Handle form submission with AJAX
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('chat-message-input-room');
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
        stopChatPolling();
        fetchNewMessages();
        chatPollInterval = setInterval(fetchNewMessages, 3000); // Poll every 3 seconds
    }

    function stopChatPolling() {
        clearInterval(chatPollInterval);
    }

    function fetchNewMessages() {
        const chatBox = document.querySelector('#supportModal .chat-box');
        const lastMessage = chatBox.querySelector('.chat-message:last-child');
        const lastId = lastMessage ? lastMessage.getAttribute('data-message-id') : 0;
        
        const url = `../get_new_messages.php?last_id=${lastId}`;

        fetch(url)
            .then(response => response.json())
            .then(messages => {
                if (messages.length > 0) {
                    messages.forEach(msg => {
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
                    chatBox.scrollTop = chatBox.scrollHeight;
                    window.housemaster_last_update = messages[messages.length - 1].created_at_timestamp;
                }
            })
            .catch(error => console.error('Chat poll error:', error));
    }

    function goToSlide(index) {
        var myCarousel = document.getElementById('roomImageCarousel');
        var carousel = bootstrap.Carousel.getOrCreateInstance(myCarousel);
        carousel.to(index);
    }
  </script>
</body>
</html>
