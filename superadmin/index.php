<?php
session_start();

// Check if the super admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

// ✅ Auto-update expired subscriptions to 'payment_due'
$conn->query("
    UPDATE admins a
    JOIN admin_subscriptions s ON a.id = s.admin_id
    SET a.account_status = 'payment_due', s.status = 'expired'
    WHERE s.end_date < CURDATE() AND a.account_status = 'active'
");

// Fetch all owners (admins) with subscription info
$result = $conn->query("
    SELECT a.id, a.name, a.email, a.boarding_code, a.account_status, a.created_at, a.payment_proof, a.payment_method, s.end_date, p.name as plan_name, p.price as plan_price 
    FROM admins a 
    LEFT JOIN admin_subscriptions s ON a.id = s.admin_id 
    LEFT JOIN subscription_plans p ON a.selected_plan_id = p.id
    ORDER BY a.created_at DESC
");
$owners = $result->fetch_all(MYSQLI_ASSOC);

// Filter pending owners for the notification area
$pending_owners = array_filter($owners, function($owner) {
    return $owner['account_status'] === 'pending';
});

// Filter non-pending owners for the main table
$non_pending_owners = array_filter($owners, function($owner) {
    return $owner['account_status'] !== 'pending';
});

// Get flash message from session for feedback
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Calculate stats
$total_owners = count($owners);
$active_owners = 0;
$payment_due_count = 0;
foreach ($owners as $o) {
    if ($o['account_status'] === 'active') $active_owners++;
    if ($o['account_status'] === 'payment_due') $payment_due_count++;
}
$pending_count = count($pending_owners);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - HouseMaster</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #05445E;
            --secondary-color: #189AB4;
            --accent-color: #75E6DA;
            --light-bg: #f4f6f9;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Poppins', sans-serif;
        }
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            z-index: 1;
            top: 0;
            left: 0;
            background-color: #05445E;
            overflow-x: hidden;
            padding-top: 20px;
        }
        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 1rem;
            color: rgba(255,255,255,0.8);
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar a.active {
            background-color: #189AB4;
            color: white;
        }
        .sidebar .logo-hold {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar .logo {
            width: 160px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            background: white;
            height: 100%;
           
        }
        .border1 {
             border-left: 6px #0d6efd solid;
        }
        .border2 {
             border-left: 6px #198754 solid;
        }
        .border3 {
             border-left: 6px #ffc107 solid;
        }
        .border4 {
             border-left: 6px #dc3545 solid;
        }
        .board{
            background-color: #05445E !important;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }
        .bg-warning {
        --bs-bg-opacity: 1;
        background-color: #fff3cd !important;
        color: #664d03 !important;
        
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            border-left: 10px #05445E solid;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0 !important;
            
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #eee;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #033245;
            border-color: #033245;
        }
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card border1 p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Total Owners</h6>
                            <h3 class="fw-bold mb-0"><?php echo $total_owners; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border2 p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Active Accounts</h6>
                            <h3 class="fw-bold mb-0"><?php echo $active_owners; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border3 p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Pending Requests</h6>
                            <h3 class="fw-bold mb-0"><?php echo $pending_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border4 p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger me-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Payment Due</h6>
                            <h3 class="fw-bold mb-0"><?php echo $payment_due_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($flash_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $flash_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending_owners)): ?>
            <div class="card mb-4 border-warning border-2">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-warning fw-bold"><i class="fas fa-exclamation-circle me-2"></i>Pending Verifications</h5>
                    <span class="badge bg-warning text-dark rounded-pill"><?php echo count($pending_owners); ?> Request(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Boarding Code</th>
                                    <th>Requested Plan</th>
                                    <th>Payment Proof</th>
                                    <th>Date Registered</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_owners as $p_owner): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p_owner['name']); ?></td>
                                        <td><?php echo htmlspecialchars($p_owner['email']); ?></td>
                                        <td><?php echo htmlspecialchars($p_owner['boarding_code']); ?></td>
                                        <td>
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($p_owner['plan_name'] ?? 'Standard'); ?></div>
                                            <small class="text-muted">₱<?php echo number_format($p_owner['plan_price'] ?? 0, 2); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($p_owner['payment_proof'])): ?>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#proofModal<?php echo $p_owner['id']; ?>">
                                                    <img src="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($p_owner['payment_proof']); ?>" alt="Proof" style="height: 40px; width: auto; border: 1px solid #ddd; border-radius: 4px;">
                                                </a>
                                                <!-- Modal -->
                                                <div class="modal fade" id="proofModal<?php echo $p_owner['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                                        <div class="modal-content border-0 shadow">
                                                            <div class="modal-header border-0 pb-0">
                                                                <h6 class="modal-title fw-bold text-secondary">Proof of Payment</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body text-center">
                                                                <div class="position-relative mb-3">
                                                                    <a href="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($p_owner['payment_proof']); ?>" target="_blank" class="d-block text-decoration-none">
                                                                        <img src="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($p_owner['payment_proof']); ?>" class="img-fluid rounded border" alt="Payment Proof" style="max-height: 250px; object-fit: contain;">
                                                                        <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75"><i class="fas fa-expand-alt me-1"></i> Zoom</span>
                                                                    </a>
                                                                </div>
                                                                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                                                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Method</small>
                                                                    <span class="badge bg-white text-dark border shadow-sm"><?php echo htmlspecialchars(ucfirst($p_owner['payment_method'] ?? 'Unknown')); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Proof</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($p_owner['created_at'])); ?></td>
                                        <td class="text-end">
                                            <form action="update_owner_status.php" method="POST" class="d-inline">
                                                <input type="hidden" name="admin_id" value="<?php echo $p_owner['id']; ?>">
                                                <input type="hidden" name="account_status" value="active">
                                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i> Approve</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog me-2"></i>Manage Owners</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Boarding Code</th>
                                <th>Status</th>
                                <th>Subscription</th>
                                <th style="width: 250px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($non_pending_owners) > 0): ?>
                                <?php foreach ($non_pending_owners as $owner): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($owner['name']); ?></td>
                                        <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                        <td><span class="badge board bg-secondary"><?php echo htmlspecialchars($owner['boarding_code']); ?></span></td>
                                        <td>
                                            <?php
                                                $status_badge = 'secondary';
                                                if ($owner['account_status'] == 'active') $status_badge = 'success';
                                                if ($owner['account_status'] == 'payment_due') $status_badge = 'warning';
                                                if ($owner['account_status'] == 'restricted') $status_badge = 'danger';
                                                if ($owner['account_status'] == 'pending') $status_badge = 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $status_badge; ?>"><?php echo ucfirst(str_replace('_', ' ', $owner['account_status'])); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($owner['account_status'] == 'active' && !empty($owner['end_date'])) {
                                                $days_left = ceil((strtotime($owner['end_date']) - time()) / 86400);
                                                if ($days_left > 0) {
                                                    echo '<span class="text-success fw-bold">' . $days_left . ' days left</span>';
                                                } else {
                                                    echo '<span class="text-danger">Expired</span>';
                                                }
                                                echo '<br><small class="text-muted">Ends: ' . date('M d, Y', strtotime($owner['end_date'])) . '</small>';
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form action="update_owner_status.php" method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="admin_id" value="<?php echo $owner['id']; ?>">
                                                <select name="account_status" class="form-select form-select-sm">
                                                    <option value="active" <?php if ($owner['account_status'] == 'active') echo 'selected'; ?>>Active</option>
                                                    <option value="payment_due" <?php if ($owner['account_status'] == 'payment_due') echo 'selected'; ?>>Payment Due</option>
                                                    <option value="restricted" <?php if ($owner['account_status'] == 'restricted') echo 'selected'; ?>>Restricted</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">No owners found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>