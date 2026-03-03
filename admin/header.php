<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if admin is logged in. If not, redirect to login page.
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

require_once __DIR__ . '/../config.php';

// 2. Fetch the latest account status for the logged-in admin on every page load.
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT account_status, payment_proof FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_account = $result->fetch_assoc();

if (!$admin_account) {
    // If admin record is not found, force logout.
    header("Location: admin-logout.php");
    exit();
}

$account_status = $admin_account['account_status'];

// Check for approval transition (Pending -> Active)
$show_approval_notification = false;
if (isset($_SESSION['prev_account_status']) && $_SESSION['prev_account_status'] === 'pending' && $account_status === 'active') {
    $show_approval_notification = true;
}
$_SESSION['prev_account_status'] = $account_status;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Styles for the non-closable modal */
    </style>
</head>
<body>
    <?php if ($account_status === 'payment_due'): ?>
        <div class="alert alert-danger text-center m-0 rounded-0" role="alert" style="z-index: 9999; position: relative;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Payment Due:</strong> Your account payment is overdue. Please settle your balance to maintain full account standing.
        </div>
    <?php endif; ?>

