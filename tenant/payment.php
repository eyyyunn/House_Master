<?php
session_start();
include __DIR__ . "/../config.php";

// Require tenant login
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-auth.php");
    exit();
}

$tenant_id = $_SESSION["tenant_id"];

$message = "";

// Handle Payment Proof Upload
if (isset($_POST['upload_proof']) && isset($_FILES['payment_proof'])) {
    $payment_id = $_POST['payment_id'];
    
    // Verify payment belongs to tenant
    $verify_stmt = $conn->prepare("SELECT id FROM payments WHERE id = ? AND tenant_id = ?");
    $verify_stmt->bind_param("ii", $payment_id, $tenant_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows > 0) {
        $file = $_FILES['payment_proof'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array($ext, $allowed)) {
                $new_filename = "proof_" . $payment_id . "_" . time() . "." . $ext;
                $target_dir = "../assets/uploads/payment_proofs/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                
                if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
                    $update_stmt = $conn->prepare("UPDATE payments SET payment_proof = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $new_filename, $payment_id);
                    $update_stmt->execute();
                    $message = "<div class='alert alert-success'>Payment proof uploaded successfully.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Failed to upload file.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Invalid file type. Allowed: JPG, PNG, PDF.</div>";
            }
        }
    }
}

// Handle month filter
$month_filter = isset($_GET['month']) ? $_GET['month'] : "";

// Build query to fetch payments
$sql = "
    SELECT id, amount, due_date, status, created_at, payment_proof
    FROM payments
    WHERE tenant_id = ?
";

$params = [$tenant_id];
$types = "i";

if (!empty($month_filter) && is_numeric($month_filter)) {
    $sql .= " AND MONTH(due_date) = ?";
    $params[] = $month_filter;
    $types .= "i";
}

$sql .= " ORDER BY due_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate remaining days (Same logic as dashboard)
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — My Payments</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="tenant.css">
  <style>
    body { background-color: #f4f6f9; }
    .main-container { max-width: 1000px; margin: 0 auto; padding: 30px 15px; }
    .page-title { color: #05445E; font-weight: 800; margin-bottom: 1.5rem; text-align: center; }
    
    .filter-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        margin-bottom: 24px;
        border: 1px solid rgba(0,0,0,0.03);
    }
    
    .payment-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        border: none;
        overflow: hidden;
    }
    
    .table thead th {
        background-color: #f8f9fa;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        padding: 15px 20px;
        border-bottom: 1px solid #edf2f9;
    }
    
    .table tbody td {
        padding: 15px 20px;
        vertical-align: middle;
        color: #344767;
        font-size: 0.95rem;
        border-bottom: 1px solid #edf2f9;
    }
    
    .table tbody tr:last-child td { border-bottom: none; }
    
    .status-badge {
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
    }
    .status-paid { background-color: #d1e7dd; color: #0f5132; }
    .status-pending { background-color: #fff3cd; color: #664d03; }
    .status-overdue { background-color: #f8d7da; color: #842029; }
    
    .amount-text { font-weight: 700; color: #05445E; }
    
    .btn-filter {
        background-color: #05445E;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-filter:hover { background-color: #032f40; color: white; }
    
    .btn-reset {
        background-color: #f1f3f5;
        color: #6c757d;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-reset:hover { background-color: #e9ecef; color: #495057; }
  </style>
</head>
<body>

<?php include("navbar.php"); ?>

<!-- Main Content -->
<div class="main-container">
  <h3 class="page-title"><i class="fas fa-credit-card me-2"></i>Payment History</h3>

  <?php if (!empty($message)): ?>
      <?php echo $message; ?>
  <?php endif; ?>

  <?php if ($days_remaining !== null): ?>
  <div class="alert <?php echo $days_remaining < 0 ? 'alert-danger' : 'alert-info'; ?> border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
      <div class="me-3">
          <i class="fas <?php echo $days_remaining < 0 ? 'fa-exclamation-circle' : 'fa-clock'; ?> fa-2x"></i>
      </div>
      <div>
          <h5 class="alert-heading fw-bold mb-1">
              <?php echo $days_remaining < 0 ? "Payment Overdue" : "Remaining Stay"; ?>
          </h5>
          <p class="mb-0">
              <?php echo $days_remaining < 0 ? "You are overdue by <strong>" . abs($days_remaining) . " days</strong>." : "You have <strong>" . $days_remaining . " days</strong> remaining until your next payment."; ?>
              <?php if($stay_until) echo " (Until $stay_until)"; ?>
          </p>
      </div>
  </div>
  <?php endif; ?>

  <!-- Filter -->
  <div class="filter-card">
    <form method="GET" class="row g-3 align-items-center">
        <div class="col-md-8">
            <label class="visually-hidden">Filter by Month</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-0"><i class="far fa-calendar-alt text-muted"></i></span>
                <select name="month" class="form-select border-0 bg-light">
                    <option value="">View All Months</option>
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
            </div>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-filter w-100">Apply Filter</button>
        </div>
        <div class="col-md-2">
          <a href="payment.php" class="btn btn-reset w-100">Reset</a>
        </div>
    </form>
  </div>

  <div class="payment-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Proof</th>
              <th>Billed On</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($payments->num_rows > 0): ?>
              <?php while ($row = $payments->fetch_assoc()): ?>
                <tr>
                  <td><span class="amount-text">₱<?php echo number_format($row['amount'], 2); ?></span></td>
                  <td>
                      <div class="d-flex align-items-center">
                          <i class="far fa-clock text-muted me-2"></i>
                          <?php echo date("M d, Y", strtotime($row['due_date'])); ?>
                      </div>
                  </td>
                  <td>
                    <?php
                        $payment_status = $row['status'];
                        $display_status = ucfirst($payment_status);
                        $badge_class = 'status-pending';
                        $icon = 'hourglass-half';

                        if ($payment_status == 'paid') {
                            $badge_class = 'status-paid';
                            $icon = 'check-circle';
                        } elseif ($payment_status == 'pending' && strtotime($row['due_date']) < time()) {
                            $display_status = 'Overdue';
                            $badge_class = 'status-overdue';
                            $icon = 'exclamation-circle';
                        }
                    ?>
                    <span class="status-badge <?php echo $badge_class; ?>">
                      <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo $display_status; ?>
                    </span>
                  </td>
                  <td>
                      <?php if (!empty($row['payment_proof'])): ?>
                          <a href="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($row['payment_proof']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>
                      <?php endif; ?>
                      
                      <?php if ($row['status'] == 'pending'): ?>
                          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadProofModal<?php echo $row['id']; ?>">
                              <i class="fas fa-upload"></i> <?php echo !empty($row['payment_proof']) ? 'Replace' : 'Upload'; ?>
                          </button>
                          
                          <!-- Upload Modal -->
                          <div class="modal fade" id="uploadProofModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content">
                                      <div class="modal-header">
                                          <h5 class="modal-title">Upload Payment Proof</h5>
                                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                      </div>
                                      <form method="POST" enctype="multipart/form-data">
                                          <div class="modal-body">
                                              <input type="hidden" name="payment_id" value="<?php echo $row['id']; ?>">
                                              <div class="mb-3">
                                                  <label class="form-label">Select Image (JPG, PNG, PDF)</label>
                                                  <input type="file" name="payment_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                              </div>
                                          </div>
                                          <div class="modal-footer">
                                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                              <button type="submit" name="upload_proof" class="btn btn-primary">Upload</button>
                                          </div>
                                      </form>
                                  </div>
                              </div>
                          </div>
                      <?php endif; ?>
                  </td>
                  <td class="text-muted small"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                  <td colspan="5" class="text-center py-5">
                      <div class="text-muted">
                          <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                          <p class="mb-0">No payment records found.</p>
                      </div>
                  </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
  </div>
</div>

<footer class="text-center mt-5 mb-3 text-muted">
  HouseMaster © 2025 — Boarding House & Dormitory Management System
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>