<?php
include __DIR__ . "/../config.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];

    // Find tenant by email
    $stmt = $conn->prepare("SELECT id FROM tenants WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $tenant = $result->fetch_assoc();
        $tenant_id = $tenant['id'];

        // Generate a unique, secure token
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600); // Token expires in 1 hour

        // Store token in the database
        $update_stmt = $conn->prepare("UPDATE tenants SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $token, $expires, $tenant_id);
        $update_stmt->execute();

        // In a real application, you would email this link.
        // For this demo, we'll display it.
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/tenant-reset-password.php?token=" . $token;
        
        $message = "A password reset link has been generated. Please click the link to reset your password: <br><a href='{$reset_link}'>{$reset_link}</a>";
        $message_type = "success";

    } else {
        $message = "No tenant account found with that email address.";
        $message_type = "danger";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; }
    .forgot-box { width: 420px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px; }
    .forgot-box h3 { text-align: center; margin-bottom: 20px; color: #05445E; font-weight: bold; }
    .btn-primary { background: #05445E; border: none; }
    .btn-primary:hover { background: #032f40; }
  </style>
</head>
<body>
  <div class="forgot-box">
    <h3>Forgot Your Password?</h3>
    <p class="text-muted text-center mb-4">Enter your email address and we will send you a link to reset your password.</p>

    <?php if (!empty($message)) : ?>
      <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($message_type !== 'success'): ?>
    <form method="POST">
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="../login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>