<?php
session_start();
require_once '../config.php';

// 1. Authentication: Ensure a super admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// 2. Validation: Check if the request method is POST and required data is present
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['admin_id']) || !isset($_POST['account_status'])) {
    // If the request is invalid, redirect back with an error (optional)
    $_SESSION['flash_message'] = "Invalid request.";
    header("Location: index.php");
    exit();
}

// 3. Sanitize and Process Input
$admin_id = (int)$_POST['admin_id'];
$new_status = $_POST['account_status'];

// A list of allowed statuses to prevent arbitrary data injection
$allowed_statuses = ['active', 'payment_due', 'restricted'];

if (in_array($new_status, $allowed_statuses)) {
    // 4. Database Update: Prepare and execute the update query
    $stmt = $conn->prepare("UPDATE admins SET account_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Owner account status updated successfully.";

        // ✅ Send System Message if activated
        if ($new_status === 'active') {
            // ✅ Fetch the selected plan details
            $plan_stmt = $conn->prepare("SELECT p.name, p.duration_days FROM admins a JOIN subscription_plans p ON a.selected_plan_id = p.id WHERE a.id = ?");
            $plan_stmt->bind_param("i", $admin_id);
            $plan_stmt->execute();
            $plan_info = $plan_stmt->get_result()->fetch_assoc();

            // Fallback defaults if something goes wrong
            $plan_name = $plan_info['name'] ?? 'Standard Monthly';
            $validity_days = $plan_info['duration_days'] ?? 30;
            
            // ✅ Generate Transaction ID
            $transaction_id = "TXN-" . strtoupper(uniqid()) . "-" . date("Ymd");
            
            // ✅ Update or Create Subscription Record
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+$validity_days days"));
             
            // Check if subscription exists
            $check_sub = $conn->prepare("SELECT id FROM admin_subscriptions WHERE admin_id = ?");
            $check_sub->bind_param("i", $admin_id);
            $check_sub->execute();
            $sub_res = $check_sub->get_result();
            
            if ($sub_res->num_rows > 0) {
                $sub_id = $sub_res->fetch_assoc()['id'];
                $upd_sub = $conn->prepare("UPDATE admin_subscriptions SET plan = ?, start_date = ?, end_date = ?, status = 'active', transaction_id = ? WHERE id = ?");
                $upd_sub->bind_param("ssssi", $plan_name, $start_date, $end_date, $transaction_id, $sub_id);
                $upd_sub->execute();
            } else {
                $ins_sub = $conn->prepare("INSERT INTO admin_subscriptions (admin_id, plan, start_date, end_date, status, transaction_id) VALUES (?, ?, ?, ?, 'active', ?)");
                $ins_sub->bind_param("issss", $admin_id, $plan_name, $start_date, $end_date, $transaction_id);
                $ins_sub->execute();
            }

            $sys_msg = "Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for {$validity_days} days.\n\nTransaction ID: {$transaction_id}";
            $msg_stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_id, message, is_read) VALUES ('system', 0, ?, ?, 0)");
            $msg_stmt->bind_param("is", $admin_id, $sys_msg);
            $msg_stmt->execute();
        }
    } else {
        $_SESSION['flash_message'] = "Error updating status: " . $conn->error;
    }
} else {
    $_SESSION['flash_message'] = "Invalid account status provided.";
}

// 5. Redirect: Go back to the main dashboard
header("Location: index.php");
exit();