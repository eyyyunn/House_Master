<?php
session_start();

// Check if the super admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

// Fetch subscription/payment records
// Since the system currently updates a single subscription record per admin, 
// this displays the current subscription status which reflects the latest payment.
$sql = "
    SELECT 
        a.id, 
        a.name, 
        a.email, 
        a.boarding_code, 
        a.payment_proof, 
        a.payment_method, 
        s.start_date, 
        s.end_date, 
        s.status AS sub_status,
        s.transaction_id,
        s.updated_at
    FROM admins a
    JOIN admin_subscriptions s ON a.id = s.admin_id
    ORDER BY s.updated_at DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments History - HouseMaster Super Admin</title>
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2"></i>Subscription Payments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Owner</th>
                                <th>Boarding Code</th>
                                <th>Payment Method</th>
                                <th>Transaction ID</th>
                                <th>Proof</th>
                                <th>Valid From</th>
                                <th>Valid Until</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['email']); ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['boarding_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars(ucfirst($row['payment_method'] ?? 'N/A')); ?></td>
                                        <td><small class="text-muted font-monospace"><?php echo htmlspecialchars($row['transaction_id'] ?? '-'); ?></small></td>
                                        <td>
                                            <?php if (!empty($row['payment_proof'])): ?>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#proofModal<?php echo $row['id']; ?>">
                                                    <img src="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($row['payment_proof']); ?>" alt="Proof" style="height: 40px; width: auto; border: 1px solid #ddd; border-radius: 4px;">
                                                </a>
                                                <!-- Modal -->
                                                <div class="modal fade" id="proofModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Payment Proof: <?php echo htmlspecialchars($row['name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body text-center bg-light">
                                                                <img src="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($row['payment_proof']); ?>" class="img-fluid" alt="Payment Proof">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                        <td>
                                            <?php
                                                $status = $row['sub_status'];
                                                $badgeClass = ($status === 'active') ? 'bg-success' : 'bg-danger';
                                                echo "<span class='badge {$badgeClass}'>" . ucfirst($status) . "</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4">No payment records found.</td></tr>
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