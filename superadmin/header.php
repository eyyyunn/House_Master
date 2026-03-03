<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if admin (owner) is logged in. If not, redirect to login page.
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

// 2. Fetch the latest account status for the logged-in admin on every page load.
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT account_status FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_account = $result->fetch_assoc();

if (!$admin_account) {
    // If admin record is not found, force logout.
    header("Location: logout.php");
    exit();
}

$account_status = $admin_account['account_status'];

// 3. Enforce account status rules.
if ($account_status === 'suspended') {
    // If suspended, immediately log them out.
    header("Location: logout.php?reason=suspended");
    exit();
}

// Only output HTML and stop execution if the account is restricted
if ($account_status === 'payment_due' || $account_status === 'pending') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include your CSS files here -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Styles for the non-closable modal */
        .payment-due-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 1050; /* High z-index to cover everything */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .payment-due-dialog {
            background: white;
            padding: 2rem 3rem;
            border-radius: 15px;
            text-align: center;
            max-width: 500px;
        }
    </style>
</head>
<body>

    <?php if ($account_status === 'payment_due'): ?>
    <div class="payment-due-backdrop">
        <div class="payment-due-dialog">
            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
            <h2 class="mt-3">Account Payment Due</h2>
            <p class="lead">Your account access is limited because of a pending payment. Please contact the system administrator to settle your account and restore full functionality.</p>
            <hr>
            <p class="mb-0">You will not be able to use the dashboard until this issue is resolved.</p>
            <div class="mt-3"><a href="logout.php" class="btn btn-sm btn-secondary">Logout</a></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($account_status === 'pending'): ?>
    <div class="payment-due-backdrop">
        <div class="payment-due-dialog">
            <i class="bi bi-hourglass-split text-info" style="font-size: 4rem;"></i>
            <h2 class="mt-3">Account Pending Verification</h2>
            <p class="lead">Your account is currently pending verification by the Super Admin. Please wait while we verify your payment details.</p>
            <hr>
            <p class="mb-0">You will be notified once your account is active.</p>
            <div class="mt-3"><a href="logout.php" class="btn btn-sm btn-secondary">Logout</a></div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>
<?php
    exit();
}
?>