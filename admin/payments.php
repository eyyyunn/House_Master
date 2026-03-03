<?php
// This header file now handles session starting, config inclusion,
// and all account status enforcement (suspended/payment_due checks).
require_once 'header.php';

$admin_id = $_SESSION['admin_id'];
$message = "";

// Display session-based messages
if (isset($_SESSION['message'])) { // This block is hit on redirect after an action
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
    // Set a timestamp to notify other open tabs that a change has occurred.
    $_SESSION['last_update_timestamp'] = time();
}

// ✅ Fetch payment status counts for the summary info
$counts_stmt = $conn->prepare("
    SELECT 
        p.status, 
        COUNT(p.id) as count
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    WHERE t.admin_id = ?
    GROUP BY p.status
");
$counts_stmt->bind_param("i", $admin_id);
$counts_stmt->execute();
$counts_result = $counts_stmt->get_result();

$payment_counts = ['paid' => 0, 'pending' => 0, 'total' => 0];
while ($row_count = $counts_result->fetch_assoc()) {
    if (isset($payment_counts[$row_count['status']])) {
        $payment_counts[$row_count['status']] = $row_count['count'];
    }
    $payment_counts['total'] += $row_count['count'];
}

// Get specific count for overdue payments
$overdue_stmt = $conn->prepare("SELECT COUNT(p.id) as count FROM payments p JOIN tenants t ON p.tenant_id = t.id WHERE t.admin_id = ? AND p.status = 'pending' AND p.due_date < CURDATE()");
$overdue_stmt->bind_param("i", $admin_id);
$overdue_stmt->execute();
$payment_counts['overdue'] = $overdue_stmt->get_result()->fetch_assoc()['count'];


// Handle "Mark as Paid" action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_as_paid']) && !in_array($account_status, ['pending', 'restricted'])) {
    $payment_id = $_POST['payment_id'];

    // Security check: ensure the admin can only update payments for their own tenants
    $stmt = $conn->prepare("
        UPDATE payments p
        JOIN tenants t ON p.tenant_id = t.id
        SET p.status = 'paid'
        WHERE p.id = ? AND t.admin_id = ?
    ");
    $stmt->bind_param("ii", $payment_id, $admin_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Success - Rebuild the query string to preserve filters
        unset($_POST['mark_as_paid']); // Remove the POST variable
        $query_string = http_build_query($_GET); // Use existing GET params
        header("Location: payments.php" . ($query_string ? "?".$query_string : ""));
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error updating payment status or payment not found.</div>";
    }
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : "";
$month_filter = isset($_GET['month']) ? $_GET['month'] : "";
$tenant_filter = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

$is_overdue_filter = ($status_filter === 'overdue');
if ($is_overdue_filter) {
    $status_filter = 'pending'; // Overdue is a subset of pending
}

// Build query to fetch payments
$sql = "
    SELECT p.id, p.amount, p.due_date, p.status, p.payment_proof, t.id AS tenant_id, t.fullname AS tenant_name, t.email AS tenant_email
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    WHERE t.admin_id = ?
";

$params = [$admin_id];
$types = "i";

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($month_filter) && is_numeric($month_filter)) {
    $sql .= " AND MONTH(p.due_date) = ?";
    $params[] = $month_filter;
    $types .= "i";
}

if ($tenant_filter > 0) {
    $sql .= " AND p.tenant_id = ?";
    $params[] = $tenant_filter;
    $types .= "i";
}

if ($is_overdue_filter) {
    $sql .= " AND p.due_date < CURDATE()";
}

$sql .= " ORDER BY p.due_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get tenant name for the header if filtering by tenant
$tenant_name_for_header = "";
if ($tenant_filter > 0) {
    $tenant_stmt = $conn->prepare("SELECT fullname FROM tenants WHERE id = ? AND admin_id = ?");
    $tenant_stmt->bind_param("ii", $tenant_filter, $admin_id);
    $tenant_stmt->execute();
    $tenant_name_for_header = $tenant_stmt->get_result()->fetch_assoc()['fullname'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Payments</title>
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
    }
    .page-header {
        margin-bottom: 2rem;
    }
    .page-title {
        color: var(--primary-color);
        font-weight: 800;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }
    .btn-primary {
      background-color: #05445E;
      border-color: #05445E;
    }
    .page-subtitle {
        color: #6c757d;
        font-size: 0.95rem;
    }

    /* Stat Cards */
    .stat-card {
        border: none;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
        
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .stat-card .card-body {
        padding: 1.5rem;
        position: relative;
        z-index: 2;
    }
    .dropdown-item {
          --bs-dropdown-link-active-bg: #05445E;
          
    }
    .dropdown-item:hover {
        background-color: #e9ecef !important; /* Manually edit hover color here */
    }
    .stat-icon-bg {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.05;
        z-index: 1;
        transform: rotate(-15deg);
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 0;
        line-height: 1.2;
    }
    .stat-label {
        color: #8898aa;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .bg-blue-soft { background-color: rgba(5, 68, 94, 0.1); color: #05445E; }
    .bg-green-soft { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .bg-yellow-soft { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .bg-red-soft { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }

    /* Content Card */
    .content-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        background: #fff;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .content-card-header {
        background: #fff;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .card-title-text {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.1rem;
        margin: 0;
    }

    /* Table Styling */
    .table thead th {
        background-color: #f8f9fa;
        color: #8898aa;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }
    .table tbody td {
        padding: 1rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        color: #525f7f;
        font-size: 0.95rem;
    }
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    .table tbody tr:hover {
        background-color: #fcfcfc;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.35em 0.8em;
        font-size: 0.75em;
        font-weight: 700;
        border-radius: 30px;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
    }
    .badge-paid { background-color: #d1e7dd; color: #0f5132; }
    .badge-pending { background-color: #fff3cd; color: #664d03; }
    .badge-overdue { background-color: #f8d7da; color: #842029; }

    /* Action Buttons */
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
        margin-right: 5px;
        border: none;
    }
    .btn-icon:hover { transform: translateY(-2px); }
    .btn-mark-paid { color: #198754; background: rgba(25, 135, 84, 0.1); }
    .btn-mark-paid:hover { background: #198754; color: #fff; }
    
    .btn-notify { color: green; background: rgba(13, 202, 240, 0.1); }
    .btn-notify:hover { background: green; color: white; }

    /* Form Controls */
    .form-select-sm {
        border-radius: 8px;
        border-color: #e0e6ed;
        font-size: 0.9rem;
    }
    .form-select:hover, .form-select-sm:hover {
        background-color: #e9ecef; /* Manually edit hover color here */
        cursor: pointer;
    }
    .form-select-sm:focus {
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 3px rgba(24, 154, 180, 0.1);
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <?php include("navbar.php"); ?>

  <!-- Main Content -->
  <div class="main">
    <div class="page-header">
        <h3 class="page-title">Payments
            <?php if ($tenant_name_for_header): ?>
                <span class="text-muted fs-5 fw-normal">/ <?php echo htmlspecialchars($tenant_name_for_header); ?></span>
            <?php endif; ?>
        </h3>
        <p class="page-subtitle">Track and manage tenant payments and billing history.</p>
    </div>

    <?php if (!empty($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <!-- Status Summary Cards -->
    <div class="row g-4 mb-4">
        
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-circle bg-green-soft me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <span class="stat-label d-block">Paid</span>
                        <h3 class="stat-value"><?php echo $payment_counts['paid']; ?></h3>
                    </div>
                    <i class="fas  stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-circle bg-yellow-soft me-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <span class="stat-label d-block">Pending</span>
                        <h3 class="stat-value"><?php echo $payment_counts['pending']; ?></h3>
                    </div>
                    <i class="fas stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-circle bg-red-soft me-3">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <span class="stat-label d-block">Overdue</span>
                        <h3 class="stat-value"><?php echo $payment_counts['overdue']; ?></h3>
                    </div>
                    <i class="fas  stat-icon-bg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card">
      <div class="content-card-header">
        <h5 class="card-title-text">Payment Records</h5>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <?php if($tenant_filter > 0): ?>
                <input type="hidden" name="tenant_id" value="<?php echo $tenant_filter; ?>">
            <?php endif; ?>
            
            <select name="status" class="form-select form-select-sm" style="width: 150px;">
                <option value="">All Statuses</option>
                <option value="pending" <?php if ($status_filter == "pending" && !$is_overdue_filter) echo "selected"; ?>>Pending</option>
                <option value="overdue" <?php if ($is_overdue_filter) echo "selected"; ?>>Overdue</option>
                <option value="paid" <?php if ($status_filter == "paid") echo "selected"; ?>>Paid</option>
            </select>
            
            <select name="month" class="form-select form-select-sm" style="width: 150px;">
                <option value="">All Months</option>
                <?php
                $months = [
                    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                ];
                foreach ($months as $num => $name) {
                    $selected = ($month_filter == $num) ? 'selected' : '';
                    echo "<option value='{$num}' {$selected}>{$name}</option>";
                }
                ?>
            </select>
            
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="payments.php" class="btn btn-sm btn-light text-muted" title="Reset"><i class="fas fa-undo"></i></a>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Proof</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                      <div class="d-flex align-items-center">
                          <div class="icon-circle bg-light text-muted me-3" style="width: 36px; height: 36px; font-size: 0.9rem; margin-bottom: 0;">
                              <i class="fas fa-user"></i>
                          </div>
                          <div>
                              <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['tenant_name']); ?></div>
                              <div class="small text-muted"><?php echo htmlspecialchars($row['tenant_email']); ?></div>
                          </div>
                      </div>
                  </td>
                  <td class="fw-bold text-dark">₱<?php echo number_format($row['amount'], 2); ?></td>
                  <td>
                      <div class="d-flex align-items-center">
                          <i class="far fa-calendar-alt text-muted me-2"></i>
                          <?php echo date("M d, Y", strtotime($row['due_date'])); ?>
                      </div>
                  </td>
                  <td> 
                    <?php
                        $payment_status = $row['status'];
                        $display_status = ucfirst($payment_status);
                        $badge_class = 'badge-pending';
                        $icon = 'clock';

                        if ($payment_status == 'paid') {
                            $badge_class = 'badge-paid';
                            $icon = 'check-circle';
                        } elseif ($payment_status == 'pending' && strtotime($row['due_date']) < time()) {
                            $display_status = 'Overdue';
                            $badge_class = 'badge-overdue';
                            $icon = 'exclamation-circle';
                        }
                    ?>
                    <span class="status-badge <?php echo $badge_class; ?>">
                      <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo $display_status; ?>
                    </span>
                  </td>
                  <td>
                      <?php if (!empty($row['payment_proof'])): ?>
                          <a href="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($row['payment_proof']); ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View Proof">
                              <i class="fas fa-image"></i> View
                          </a>
                      <?php else: ?>
                          <span class="text-muted small">-</span>
                      <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if ($row['status'] == 'pending' && !in_array($account_status, ['pending', 'restricted'])): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Mark this payment as paid?')">
                            <input type="hidden" name="payment_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="mark_as_paid" class="btn-icon btn-mark-paid" title="Mark as Paid">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>

                        <div class="d-inline-block dropdown">
                            <button type="button" class="btn-icon btn-notify dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Send Reminder">
                                <i class="fas fa-bell"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php
                                    $today = new DateTime();
                                    $dueDate = new DateTime($row['due_date']);
                                    $days_remaining = (int)$today->diff($dueDate)->format('%r%a');
                                    $amount_formatted = number_format($row['amount']);
                                    $due_date_formatted = date("M d, Y", strtotime($row['due_date']));
                                    $message_template = "Hi " . htmlspecialchars($row['tenant_name']) . ", this is a reminder for your payment of ₱" . $amount_formatted . " due on " . $due_date_formatted . ". Thank you. - HouseMaster";
                                ?>
                                <li><a class="dropdown-item" href="send_manual_sms.php?<?php echo http_build_query(array_merge($_GET, ['tenant_id' => $row['tenant_id'], 'days_remaining' => $days_remaining])); ?>" onclick="return confirm('Send an SMS payment reminder to this tenant?')"><i class="fas fa-comment-sms fa-fw me-2"></i>Via SMS</a></li>
                                <li><a class="dropdown-item" href="send_email_reminder.php?<?php echo http_build_query(array_merge($_GET, ['tenant_id' => $row['tenant_id']])); ?>" onclick="return confirm('Send an email payment reminder to this tenant?')"><i class="fas fa-envelope fa-fw me-2"></i>Via Email</a></li>
                                <li>
                                    <form action="send_platform_message.php" method="POST" onsubmit="return confirm('Send this platform message reminder?');" style="display: inline;">
                                        <input type="hidden" name="tenant_id" value="<?php echo $row['tenant_id']; ?>">
                                        <input type="hidden" name="message" value="<?php echo htmlspecialchars($message_template); ?>">
                                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-paper-plane fa-fw me-2"></i>Via Platform
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                      <span class="text-muted small fst-italic">No actions</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-5 text-muted">No payments found matching your criteria.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <footer class="mt-5 text-center text-muted small">HouseMaster © 2025 — Boarding House & Dormitory Management System</footer>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
 
  <script>
    
    window.housemaster_last_update = <?php echo isset($_SESSION['last_update_timestamp']) ? $_SESSION['last_update_timestamp'] : 0; ?>;
  </script>
  <script src="../assets/js/autoupdate.js"></script>
</body>
</html>
