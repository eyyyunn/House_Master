<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = intval($_POST['plan_id']);
    $payment_method = $_POST['payment_method'];
    
    // File Upload
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = "proof_" . $admin_id . "_" . time() . "." . $file_ext;
            $upload_dir = "../assets/uploads/payment_proofs/";
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                
                $stmt = $conn->prepare("UPDATE admins SET payment_proof = ?, payment_method = ?, selected_plan_id = ?, account_status = 'pending' WHERE id = ?");
                $stmt->bind_param("ssii", $new_filename, $payment_method, $plan_id, $admin_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "<div class='alert alert-success'>Payment proof uploaded successfully! Please wait for verification.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>Database error: " . $conn->error . "</div>";
                }
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Failed to move uploaded file.</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Invalid file type. Only JPG, PNG, and PDF allowed.</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Please select a valid file.</div>";
    }
}

header("Location: dashboard.php");
exit();