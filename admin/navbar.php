
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    
    include_once __DIR__ . "/../config.php";
}

$admin_id_for_nav = $_SESSION['admin_id'] ?? 0;

// 1. Count unread messages for the admin
$unread_stmt = $conn->prepare("SELECT COUNT(id) AS count FROM messages WHERE receiver_id = ? AND (sender_type = 'tenant' OR sender_type = 'system') AND is_read = 0");
$unread_stmt->bind_param("i", $admin_id_for_nav);
$unread_stmt->execute();
$unread_messages_count = $unread_stmt->get_result()->fetch_assoc()['count'];

// 2. Count pending tenants for the admin
$pending_stmt = $conn->prepare("SELECT COUNT(id) AS count FROM tenants WHERE admin_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $admin_id_for_nav);
$pending_stmt->execute();
$pending_tenants_count = $pending_stmt->get_result()->fetch_assoc()['count'];

// 3. Count overdue payments for the admin
$overdue_stmt = $conn->prepare("SELECT COUNT(p.id) AS count FROM payments p JOIN tenants t ON p.tenant_id = t.id WHERE t.admin_id = ? AND p.status = 'pending' AND p.due_date < CURDATE()");
$overdue_stmt->bind_param("i", $admin_id_for_nav);
$overdue_stmt->execute();
$overdue_payments_count = $overdue_stmt->get_result()->fetch_assoc()['count'];

// 4. Fetch current plan details
$plan_stmt = $conn->prepare("SELECT selected_plan_id FROM admins WHERE id = ?");
$plan_stmt->bind_param("i", $admin_id_for_nav);
$plan_stmt->execute();
$current_plan_id = $plan_stmt->get_result()->fetch_assoc()['selected_plan_id'] ?? 1;

// 5. Fetch all available plans for the dropdown
$all_plans_result = $conn->query("SELECT * FROM subscription_plans ORDER BY price ASC");
$all_plans = $all_plans_result->fetch_all(MYSQLI_ASSOC);

// 6. Fetch Global Payment Settings
$settings_result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$payment_settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $payment_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<?php if (isset($account_status) && $account_status === 'pending'): ?>
<div class="alert alert-warning text-center m-0 rounded-0 fixed-top alert-custom-pos">
    <?php if (empty($admin_account['payment_proof'])): ?>
        <i class="fas fa-exclamation-circle"></i> <strong>Account Pending:</strong> You have read-only access. <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#paymentVerificationModal">Click here to verify your payment</a> to unlock full access.
    <?php else: ?>
        <i class="fas fa-hourglass-half"></i> <strong>Verification In Progress:</strong> You have read-only access while we verify your payment proof.
    <?php endif; ?>
</div>
<style>.main, .main-content { margin-top: 40px; }</style>
<?php endif; ?>

<?php if (isset($account_status) && $account_status === 'restricted'): ?>
<div class="alert alert-danger text-center m-0 rounded-0 fixed-top alert-custom-pos">
    <i class="fas fa-ban"></i> <strong>Account Restricted:</strong> Your account has been restricted. You have read-only access. <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#paymentVerificationModal">Click here to renew your subscription</a>.
</div>
<style>.main, .main-content { margin-top: 40px; }</style>
<?php endif; ?>

<?php if (isset($show_approval_notification) && $show_approval_notification): ?>
<div class="alert alert-success alert-dismissible fade show text-center m-0 rounded-0 fixed-top alert-custom-pos" role="alert">
    <i class="fas fa-check-circle"></i> <strong>Congratulations!</strong> Your account has been approved by the Super Admin. You now have full access.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<style>.main, .main-content { margin-top: 40px; }</style>
<?php endif; ?>

<div class="sidebar d-none d-md-block">
  <div class="logo-hold">
    <img class="logo"  src="../assets/img/logo remove.png" alt="" srcset="">

</div>
  <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  <a href="tenants.php?status=pending"><i class="fas fa-users"></i> Tenants Profile <?php if ($pending_tenants_count > 0) echo '<span class="notification-badge"></span>'; ?></a>
  <a href="rooms.php"><i class="fas fa-home"></i> Room Management</a>
  <a href="payments.php?status=overdue"><i class="fas fa-money-bill"></i> Payments <?php if ($overdue_payments_count > 0) echo '<span class="notification-badge"></span>'; ?></a>
  <a href="messages.php"><i class="fas fa-envelope"></i> Messages <?php if ($unread_messages_count > 0) echo '<span class="notification-badge"></span>'; ?></a>
  <a href="notices.php"><i class="fas fa-bullhorn"></i> Announcements</a>
  <a href="admin-logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav d-md-none">
    <a href="dashboard.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>Home</span>
    </a>
    <a href="tenants.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'tenants.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Tenants</span>
        <?php if ($pending_tenants_count > 0) echo '<span class="notification-dot"></span>'; ?>
    </a>
    <a href="rooms.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>">
        <i class="fas fa-door-open"></i>
        <span>Rooms</span>
    </a>
    <a href="payments.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
        <i class="fas fa-money-bill"></i>
        <span>Pay</span>
        <?php if ($overdue_payments_count > 0) echo '<span class="notification-dot"></span>'; ?>
    </a>
    <a href="messages.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
        <i class="fas fa-envelope"></i>
        <span>Chat</span>
        <?php if ($unread_messages_count > 0) echo '<span class="notification-dot"></span>'; ?>
    </a>
    <a href="admin-logout.php" class="nav-item-mobile">
        <i class="fas fa-sign-out-alt"></i>
        <span>Exit</span>
    </a>
</nav>

<style>
    /* Alert positioning */
    .alert-custom-pos {
        z-index: 1030;
        left: 240px;
    }
    /* Mobile Bottom Nav Styles */
    @media (max-width: 768px) {
        .alert-custom-pos {
            left: 0;
        }
        .main, .main-content {
            margin-left: 0 !important;
            padding-bottom: 80px !important; /* Space for bottom nav */
        }
        .mobile-bottom-nav {
            display: flex;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            height: 65px;
            z-index: 1040;
            justify-content: space-around;
            align-items: center;
            padding-bottom: env(safe-area-inset-bottom); /* For iPhone X+ */
        }
        .nav-item-mobile {
            text-align: center;
            color: #6c757d;
            text-decoration: none;
            font-size: 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            position: relative;
        }
        .nav-item-mobile i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .nav-item-mobile.active {
            color: #05445E;
            font-weight: 600;
        }
        .notification-dot {
            position: absolute;
            top: 10px;
            right: 25%; /* Adjust based on icon width */
            width: 8px;
            height: 8px;
            background-color: #dc3545;
            border-radius: 50%;
            border: 1px solid #fff;
        }
    }
</style>

<!-- Payment Verification Modal -->
<div class="modal fade" id="paymentVerificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header border-0 d-flex flex-column align-items-center justify-content-center pt-4 pb-0 " style="background-color: #f8f9fa;  ">
                <img src="../assets/img/logo white.png" alt="HouseMaster Logo" style="width: 140px; height: auto; margin-bottom: 10px;">
                <h5 class="modal-title fw-bold" style="color: #05445E;">Payment Verification</h5>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-start" style="background-color: #f8f9fa;">
                <form action="admin-payment.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small text-uppercase">Choose Subscription Plan</label>
                        <select name="plan_id" id="planSelector" class="form-select shadow-none" style="border-color: #ced4da;" onchange="updatePlanDetails()">
                            <?php foreach ($all_plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>" 
                                    data-price="<?php echo $plan['price']; ?>" 
                                    data-duration="<?php echo $plan['duration_days']; ?>"
                                    <?php if ($plan['id'] == $current_plan_id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($plan['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-info border-0 shadow-sm mb-3" id="planDetailsAlert">
                        <!-- Details injected via JS -->
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small text-uppercase">Select Payment Method</label>
                        <select id="modalPaymentMethod" class="form-select shadow-none" onchange="toggleModalPaymentDetails()" style="border-color: #ced4da;">
                            <option value="" selected disabled>Choose an option...</option>
                            <option value="gcash">GCash</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>

                    <div id="modalGcashDetails" class="mb-4 p-3 bg-white rounded border shadow-sm d-none">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-mobile-alt fa-lg me-2" style="color: #05445E;"></i>
                            <h6 class="fw-bold mb-0" style="color: #05445E;">GCash Payment</h6>
                        </div>
                        <p class="small text-muted mb-2">Please send your subscription payment to:</p>
                        <ul class="list-unstyled small mb-0 ps-2 border-start border-3" style="border-color: #05445E !important;">
                            <li class="mb-1"><span class="text-muted">Number:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment_settings['gcash_number'] ?? 'N/A'); ?></strong></li>
                            <li><span class="text-muted">Name:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment_settings['gcash_name'] ?? 'N/A'); ?></strong></li>
                        </ul>
                    </div>

                    <div id="modalBankDetails" class="mb-4 p-3 bg-white rounded border shadow-sm d-none">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-university fa-lg me-2" style="color: #05445E;"></i>
                            <h6 class="fw-bold mb-0" style="color: #05445E;">Bank Transfer</h6>
                        </div>
                        <p class="small text-muted mb-2">Please deposit your payment to:</p>
                        <ul class="list-unstyled small mb-0 ps-2 border-start border-3" style="border-color: #05445E !important;">
                            <li class="mb-1"><span class="text-muted">Bank:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment_settings['bank_name'] ?? 'N/A'); ?></strong></li>
                            <li class="mb-1"><span class="text-muted">Account No:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment_settings['bank_account_num'] ?? 'N/A'); ?></strong></li>
                            <li><span class="text-muted">Account Name:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment_settings['bank_account_name'] ?? 'N/A'); ?></strong></li>
                        </ul>
                    </div>

                    <div id="modalUploadSection" class="d-none">
                        <hr class="my-4 text-muted">
                        <p class="text-muted text-center mb-3 small">Upload a screenshot of your successful payment.</p>
                        <input type="hidden" name="payment_method" id="modalPaymentMethodInput">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Payment Screenshot</label>
                            <input type="file" name="payment_proof" class="form-control shadow-none" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2" style="background-color: #05445E; border: none;">Submit & Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleModalPaymentDetails() {
    const method = document.getElementById('modalPaymentMethod').value;
    const gcash = document.getElementById('modalGcashDetails');
    const bank = document.getElementById('modalBankDetails');
    const upload = document.getElementById('modalUploadSection');
    const paymentMethodInput = document.getElementById('modalPaymentMethodInput');

    gcash.classList.add('d-none');
    bank.classList.add('d-none');
    upload.classList.add('d-none');

    if (method === 'gcash') {
        gcash.classList.remove('d-none');
        upload.classList.remove('d-none');
        paymentMethodInput.value = 'gcash';
    } else if (method === 'bank') {
        bank.classList.remove('d-none');
        upload.classList.remove('d-none');
        paymentMethodInput.value = 'bank';
    }
}

function updatePlanDetails() {
    const selector = document.getElementById('planSelector');
    const selectedOption = selector.options[selector.selectedIndex];
    const price = parseFloat(selectedOption.getAttribute('data-price')).toLocaleString('en-US', {minimumFractionDigits: 2});
    const duration = selectedOption.getAttribute('data-duration');
    const name = selectedOption.text;

    const html = `
        <div class="d-flex justify-content-between align-items-center">
            <strong class="text-dark">Amount to Pay</strong>
            <span class="badge bg-primary fs-6">₱${price}</span>
        </div>
        <div class="small text-muted mt-1">Valid for ${duration} days</div>
    `;
    document.getElementById('planDetailsAlert').innerHTML = html;
}
// Initialize details on load
document.addEventListener('DOMContentLoaded', updatePlanDetails);
</script>
