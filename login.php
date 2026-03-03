<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $found = false;

        // 1. Check Super Admin
        if (!$found) {
            $stmt = $conn->prepare("SELECT id, password FROM super_admins WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $_SESSION['super_admin_id'] = $row['id'];
                    header("Location: superadmin/index.php");
                    exit();
                }
                $found = true; // Email found but password might be wrong
            }
        }

        // 2. Check Admin (Landlord)
        if (!$found) {
            $stmt = $conn->prepare("SELECT id, password, name, boarding_code FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['boarding_code'] = $row['boarding_code'];
                    header("Location: admin/dashboard.php");
                    exit();
                }
                $found = true;
            }
        }

        // 3. Check Tenant
        if (!$found) {
            $stmt = $conn->prepare("SELECT id, password, fullname, status FROM tenants WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    if ($row['status'] === 'inactive') {
                        $error = "Your account has been deactivated. Please contact your landlord.";
                    } elseif ($row['status'] === 'pending') {
                        $error = "Your account is pending approval.";
                    } else {
                        $_SESSION['tenant_id'] = $row['id'];
                        $_SESSION['tenant_name'] = $row['fullname'];
                        header("Location: tenant/dashboard.php");
                        exit();
                    }
                    $found = true; // Stop further checks if we found the user but had a status error
                } else {
                    $found = true; // Email found, password wrong
                }
            }
        }

        if (empty($error)) {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body { background: #f4f6f9; font-family: 'Poppins', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; }
    .login-card { width: 100%; max-width: 400px; background: #ffffff; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 40px; }
    .login-header { text-align: center; margin-bottom: 30px; }
    .login-header img { width: 180px; margin-bottom: 15px; }
    .login-header h3 { color: #05445E; font-weight: 600; font-size: 1.5rem; }
    .form-control { border-radius: 10px; padding: 12px; }
    .form-control:focus { border-color: #189AB4; box-shadow: 0 0 0 0.25rem rgba(24, 154, 180, 0.25); }
    .btn-primary { background-color: #05445E; border-color: #05445E; border-radius: 10px; padding: 12px; font-weight: 500; width: 100%; }
    .btn-primary:hover { background-color: #032f40; border-color: #032f40; }
    .footer-links { text-align: center; margin-top: 20px; font-size: 0.9rem; }
    .footer-links a { color: #189AB4; text-decoration: none; font-weight: 500; }
    .footer-links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-header">
      <img src="assets/img/logo white.png" alt="HouseMaster Logo">
      <h3>Welcome Back</h3>
      <p class="text-muted">Login to your account</p>
    </div>

    <?php if (!empty($error)) : ?>
      <div class="alert alert-danger text-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <input type="text" name="email" class="form-control" placeholder="Email Address or Username" required autofocus>
      </div>
      <div class="mb-4">
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      <button type="submit" class="btn btn-primary">Log In</button>
      <div class="footer-links">
        <p class="mb-1">New here? <a href="tenant/tenant-auth.php?action=signup">Register as Tenant</a></p>
        <p class="mb-0"><a href="admin/admin-register.php">Register as Landlord</a></p>
      </div>
    </form>
  </div>
</body>
</html>