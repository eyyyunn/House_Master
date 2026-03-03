<?php
include __DIR__ . "/../config.php";

$token = $_GET['token'] ?? '';
$message = "";
$message_type = "danger";
$show_form = false;

if (empty($token)) {
    $message = "Invalid or missing password reset token.";
} else {
    // Find admin by token
    $stmt = $conn->prepare("SELECT id FROM admins WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $admin_id = $admin['id'];
        $show_form = true;

        
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];

            if ($password !== $password_confirm) {
                $message = "Passwords do not match.";
            } elseif (strlen($password) < 6) {
                $message = "Password must be at least 6 characters long.";
            } else {
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                
                $update_stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($update_stmt->execute()) {
                    $message = "Your password has been reset successfully! You can now log in.";
                    $message_type = "success";
                    $show_form = false;
                } else {
                    $message = "An error occurred. Please try again.";
                }
                $update_stmt->close();
            }
        }
    } else {
        $message = "This password reset token is invalid or has expired.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; }
    .reset-box { width: 420px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px; }
    .reset-box h3 { text-align: center; margin-bottom: 20px; color: #05445E; font-weight: bold; }
    .btn-primary { background: #05445E; border: none; }
    .btn-primary:hover { background: #032f40; }
  </style>
</head>
<body>
  <div class="reset-box">
    <h3>Reset Your Password</h3>

    <?php if (!empty($message)) : ?>
      <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <form method="POST">
      <div class="mb-3">
        <label for="password" class="form-label">New Password</label>
        <input type="password" name="password" id="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Reset Password</button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="../login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>