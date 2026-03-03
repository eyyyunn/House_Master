<?php
// This header file now handles session starting, config inclusion,
// and all account status enforcement (suspended/payment_due checks).
require_once 'header.php';

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$boarding_code = $_SESSION['boarding_code'];

// ===================================================================
// == Data Correction for Tenant Boarding Codes
// ===================================================================
// This script ensures all tenants associated with this admin have the
// correct boarding_code. This fixes any data inconsistencies from
// previous versions or signup issues.
$correction_stmt = $conn->prepare("
    UPDATE tenants 
    SET boarding_code = ? 
    WHERE admin_id = ? AND (boarding_code IS NULL OR boarding_code != ?)
");
$correction_stmt->bind_param("sis", $boarding_code, $admin_id, $boarding_code);
$correction_stmt->execute();
// ===================================================================
// == Automatic Billing Engine
// ===================================================================
// This logic runs every time the dashboard is loaded. It finds tenants
// whose last bill is due and generates the next one.

// 1. Find all active tenants for the current admin who have a room assigned.
$tenants_to_check_stmt = $conn->prepare("
    SELECT t.id, r.rental_rate FROM tenants t
    JOIN tenant_rooms tr ON t.id = tr.tenant_id
    JOIN rooms r ON tr.room_id = r.id
    WHERE t.admin_id = ? AND t.status = 'active'
");
$tenants_to_check_stmt->bind_param("i", $admin_id);
$tenants_to_check_stmt->execute();
$tenants_to_check = $tenants_to_check_stmt->get_result();

while ($tenant = $tenants_to_check->fetch_assoc()) {
    // 2. For each tenant, find their latest payment's due date.
    $latest_payment_stmt = $conn->prepare("SELECT MAX(due_date) as latest_due FROM payments WHERE tenant_id = ?");
    $latest_payment_stmt->bind_param("i", $tenant['id']);
    $latest_payment_stmt->execute();
    $latest_due_date = $latest_payment_stmt->get_result()->fetch_assoc()['latest_due'];

    // 3. If the latest due date has passed, generate a new bill for the next month.
    if ($latest_due_date && strtotime($latest_due_date) < time()) {
        $next_due_date = date('Y-m-d', strtotime($latest_due_date . ' +1 month'));
        $insert_bill_stmt = $conn->prepare("INSERT INTO payments (tenant_id, admin_id, boarding_code, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insert_bill_stmt->bind_param("iisds", $tenant['id'], $admin_id, $boarding_code, $tenant['rental_rate'], $next_due_date);
        $insert_bill_stmt->execute();
    }
}

// ✅ Fetch scoped data from database using prepared statements
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tenants WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$tenantsCount = $stmt->get_result()->fetch_assoc()["total"];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM rooms WHERE boarding_code = ?");
$stmt->bind_param("s", $boarding_code);
$stmt->execute();
$roomsCount = $stmt->get_result()->fetch_assoc()["total"];

$stmt = $conn->prepare("SELECT IFNULL(SUM(p.amount),0) AS total FROM payments p JOIN tenants t ON p.tenant_id = t.id WHERE t.admin_id = ? AND p.status='paid'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$paymentsTotal = $stmt->get_result()->fetch_assoc()["total"];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notices WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$noticesCount = $stmt->get_result()->fetch_assoc()["total"];

// ✅ Fetch overdue payments count
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM payments p JOIN tenants t ON p.tenant_id = t.id WHERE t.admin_id = ? AND p.status='pending' AND p.due_date < CURDATE()");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$overdueCount = $stmt->get_result()->fetch_assoc()["total"];

// Recent notices (limit 5)
$stmt = $conn->prepare("SELECT * FROM notices WHERE admin_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$recentNotices = $stmt->get_result();

// Rooms list (limit 5)
$stmt = $conn->prepare("SELECT * FROM rooms WHERE boarding_code = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("s", $boarding_code);
$stmt->execute();
$recentRooms = $stmt->get_result();

// Assigned tenants with rooms
$stmt = $conn->prepare("SELECT t.fullname, r.room_label FROM tenants t JOIN tenant_rooms tr ON t.id = tr.tenant_id JOIN rooms r ON tr.room_id = r.id WHERE t.admin_id = ? AND t.status = 'active' ORDER BY r.room_label ASC");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$assignedTenants = $stmt->get_result();

// Pending tenants
$stmt = $conn->prepare("SELECT fullname FROM tenants WHERE admin_id = ? AND status = 'pending' ORDER BY created_at DESC");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$pendingTenants = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="side.css">
  <style>
    /* Main content should not overlap sidebar */
    .main-content {
      margin-left: 240px; /* Adjust this if your sidebar has a different width */
      padding: 20px;
      background: #f7f9fc;
      min-height: 100vh;
      transition: margin-left 0.3s ease;
    }
    .bg-primary {
    --bs-bg-opacity: 1;
    background-color: #05445E !important;
    }
    .bg-secondary {
    --bs-bg-opacity: 1;
    background-color: #05445E !important;
    
    }

    .btn-outline-primary

    {
    --bs-btn-color: #05445E;
    --bs-btn-border-color: #05445E;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #05445E;
    --bs-btn-hover-border-color: #05445E;
    --bs-btn-focus-shadow-rgb: 13, 110, 253;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #05445E;
    --bs-btn-active-border-color: #05445E;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #05445E;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #05445E;
    --bs-gradient: none;
    }
    .form-select:hover {
      background-color: #69696959 !important;
    }


    /* Modernized dashboard cards */
    .card {
      border: none;
      border-radius: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: box-shadow 0.2s ease;
    }
    .card:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    .dashboard-stat {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
    }
    .dashboard-label {
      font-size: 1rem;
      font-weight: 500;
      color: #6c757d;
    }
    .icon-circle {
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      font-size: 1.5rem;
      color: #fff;
    }
    .icon-tenants { background: #3498db; }
    .icon-rooms { background: #9b59b6; }
    .icon-payments { background: #2ecc71; }
    .icon-notices { background: #e67e22; }
    .icon-overdue { background: #e74c3c; }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      .dashboard-stat {
        font-size: 1.5rem;
      }
      .dashboard-label {
        font-size: 0.8rem;
      }
      .icon-circle {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <?php include "navbar.php"; ?>

  <div class="main-content">
    <div class="container-fluid mt-4">

      <!-- Welcome and Boarding Code -->
      <div class="card p-3 mb-4 bg-light border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-0">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h4>
                <p class="text-muted mb-0">Here's the overview of your boarding house.</p>
            </div>
            <div class="text-md-end">
                <h5 class="mb-0">Your Boarding House Code: <span class="badge bg-primary fs-5"><?php echo htmlspecialchars($boarding_code); ?></span></h5>
               
            </div>
        </div>
      </div>

      <div class="row g-3 g-md-4">

        <!-- Total Tenants -->
        <div class="col-6 col-md-3">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
              <div class="icon-circle icon-tenants me-3">
                <i class="fa-solid fa-users"></i>
              </div>
              <div>
                <div class="dashboard-stat"><?php echo $tenantsCount; ?></div>
                <div class="dashboard-label">Tenants</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Rooms -->
        <div class="col-6 col-md-3">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
              <div class="icon-circle icon-rooms me-3">
                <i class="fa-solid fa-door-open"></i>
              </div>
              <div>
                <div class="dashboard-stat"><?php echo $roomsCount; ?></div>
                <div class="dashboard-label">Rooms</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payments Collected -->
        <div class="col-6 col-md-3">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
              <div class="icon-circle icon-payments me-3">
                <i class="fa-solid fa-wallet"></i>
              </div>
              <div>
                <div class="dashboard-stat">₱<?php echo number_format($paymentsTotal, 2); ?></div>
                <div class="dashboard-label">Payments Collected</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Overdue Payments -->
        <div class="col-6 col-md-3">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
              <div class="icon-circle icon-overdue me-3">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </div>
              <div>
                <div class="dashboard-stat"><?php echo $overdueCount; ?></div>
                <div class="dashboard-label">Overdue Payments</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Notices & Rooms side by side -->
      <div class="row mt-3 mt-md-4 g-3 g-md-4">
        <!-- Recent Notices -->
        <div class="col-12 col-md-6">
          <div class="card p-3 h-100">
            <h5 class="mb-3"><i class="fa-solid fa-bullhorn me-2"></i> Recent Announcements</h5>
            <ul class="list-group list-group-flush">
              <?php if ($recentNotices->num_rows > 0): ?>
                <?php while($n = $recentNotices->fetch_assoc()): ?> 
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                      <strong><?php echo htmlspecialchars($n['title']); ?></strong><br>
                      <small class="text-muted"><?php echo date("M d, Y", strtotime($n['created_at'])); ?></small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#noticeModalAdmin<?php echo $n['id']; ?>"><i class="fas fa-eye"></i> View</button>
                  </li>
                <?php endwhile; ?>
              <?php else: ?>
                <li class="list-group-item text-muted">No announcements yet.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <!-- Recent Rooms -->
        <div class="col-12 col-md-6">
          <div class="card p-3 h-100">
            <h5 class="mb-3"><i class="fa-solid fa-door-open me-2"></i> Recently Added Rooms</h5>
            <ul class="list-group list-group-flush">
              <?php if ($recentRooms->num_rows > 0): ?>
                <?php while($r = $recentRooms->fetch_assoc()): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($r['room_label']); ?>
                    <span class="badge bg-secondary">Capacity: <?php echo $r['capacity']; ?></span>
                  </li>
                <?php endwhile; ?>
              <?php else: ?>
                <li class="list-group-item text-muted">No rooms added yet.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <!-- Assigned & Unassigned Tenants Section -->
<div class="row mt-3 mt-md-4 g-3 g-md-4">
  <!-- Assigned Tenants -->
  <div class="col-12 col-md-6">
    <div class="card p-3">
      <h5 class="mb-3"><i class="fa-solid fa-user-check me-2"></i> Assigned Tenants & Rooms</h5>
      <ul class="list-group list-group-flush">
        <?php if ($assignedTenants->num_rows > 0): ?>
          <?php while($a = $assignedTenants->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?php echo htmlspecialchars($a['fullname']); ?>
              <span class="badge bg-primary"><?php echo htmlspecialchars($a['room_label']); ?></span>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="list-group-item text-muted">No tenants assigned yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Pending Tenants -->
  <div class="col-12 col-md-6">
    <div class="card p-3">
      <h5 class="mb-3"><i class="fa-solid fa-user-clock me-2"></i> Pending Tenants</h5>
      <ul class="list-group list-group-flush">
        <?php if ($pendingTenants->num_rows > 0): ?>
          <?php while($p = $pendingTenants->fetch_assoc()): ?>
            <li class="list-group-item">
              <?php echo htmlspecialchars($p['fullname']); ?>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="list-group-item text-muted">No pending tenants.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
      </div>

    </div>
  </div>

  <!-- Announcement Modals -->
  <?php
    if ($recentNotices->num_rows > 0) {
      $recentNotices->data_seek(0); // Reset result pointer
      while($n = $recentNotices->fetch_assoc()):
  ?>
  <div class="modal fade" id="noticeModalAdmin<?php echo $n['id']; ?>" tabindex="-1" aria-labelledby="noticeModalLabelAdmin<?php echo $n['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="noticeModalLabelAdmin<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['title']); ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><small class="text-muted">Posted on: <?php echo date("F j, Y, g:i a", strtotime($n['created_at'])); ?></small></p>
          <hr>
          <p><?php echo nl2br(htmlspecialchars($n['body'])); ?></p>
        </div>
      </div>
    </div>
  </div>
  <?php endwhile; } ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    
    window.housemaster_last_update = <?php echo isset($_SESSION['last_update_timestamp']) ? $_SESSION['last_update_timestamp'] : 0; ?>;
  </script>
  <script src="../assets/js/autoupdate.js"></script>
</body>
</html>
