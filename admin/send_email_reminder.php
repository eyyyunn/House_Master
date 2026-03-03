<?php
session_start();
include __DIR__ . "/../config.php";


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require __DIR__ . '/../vendor/autoload.php';

// Require admin login
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

if ($tenant_id === 0) {
    // It's good practice to handle invalid input
    $_SESSION['message'] = "<div class='alert alert-danger'>No tenant specified.</div>";
    header("Location: payments.php");
    exit();
}

// 1. Find the tenant's next due payment and contact details
$stmt = $conn->prepare("
    SELECT t.fullname, t.email, p.amount, p.due_date
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    WHERE p.tenant_id = ? AND t.admin_id = ? AND p.status = 'pending'
    ORDER BY p.due_date ASC
    LIMIT 1
");
$stmt->bind_param("ii", $tenant_id, $admin_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    $_SESSION['message'] = "<div class='alert alert-warning'>No pending payment found for this tenant.</div>";
    header("Location: payments.php");
    exit();
}

if (empty($payment['email'])) {
    $_SESSION['message'] = "<div class='alert alert-danger'>Tenant does not have an email address on record.</div>";
    header("Location: payments.php");
    exit();
}

// 2. Construct the email message
$due_date_formatted = date("F d, Y", strtotime($payment['due_date']));
$amount_formatted = number_format($payment['amount'], 2);

$subject = "Payment Reminder from HouseMaster";
$body = "
    <p>Hi " . htmlspecialchars($payment['fullname']) . ",</p>
    <p>This is a friendly reminder that your payment of <strong>₱" . $amount_formatted . "</strong> is due on <strong>" . $due_date_formatted . "</strong>.</p>
    <p>Thank you,<br>HouseMaster</p>
";

// 3. Send the email using PHPMailer with Gmail SMTP
$mail = new PHPMailer(true);

try {
    
    // Using constants from config.php
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); // Use constants from config.php
    $mail->addAddress($payment['email'], $payment['fullname']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body); // Plain text version

    $mail->send();
    $_SESSION['message'] = "<div class='alert alert-success'>Email reminder sent successfully to " . htmlspecialchars($payment['fullname']) . ".</div>";
    $_SESSION['last_update_timestamp'] = time(); // Notify of update
} catch (Exception $e) {
    // Log the detailed error for debugging, but show a generic message to the user.
    error_log("Mailer Error: {$mail->ErrorInfo}"); // This error is logged to your PHP error log file (e.g., php_error.log)
    $_SESSION['message'] = "<div class='alert alert-danger'>Failed to send email. Please check your configuration. PHPMailer Error: {$mail->ErrorInfo}</div>";
}

// 4. Redirect back to the payments page

$redirect_params = $_GET;
unset($redirect_params['tenant_id']);

$query_string = http_build_query($redirect_params);
header("Location: payments.php" . ($query_string ? "?".$query_string : ""));
exit();