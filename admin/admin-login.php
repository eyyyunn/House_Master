<?php
session_start();
include __DIR__ . "/../config.php";

$error = ""; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['boarding_code'] = $admin['boarding_code'];
            $_SESSION['admin_name'] = $admin['name'];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Admin not found.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      background: white;
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 0;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      background: #ffffff;
      border-radius: 15px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      padding: 40px;
      animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .login-header img {
      width: 200px;
      margin-bottom: 15px;
      border-radius: 7px;
    }
    .login-header h3 {
      color: #05445E;
      font-weight: 600;
      font-size: 1.5rem;
    }
    .form-floating .form-control {
      border-radius: 10px;
      border: 1px solid #dee2e6;
    }
    .form-floating .form-control:focus {
      border-color: #189AB4;
      box-shadow: 0 0 0 0.25rem rgba(24, 154, 180, 0.25);
    }
    .btn-primary {
      background-color: #05445E;
      border-color: #05445E;
      border-radius: 10px;
      padding: 12px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      background-color: #032f40;
      border-color: #032f40;
      transform: translateY(-2px);
    }
    .footer-links {
      text-align: center;
      margin-top: 20px;
      font-size: 0.9rem;
    }
    .footer-links a {
      color: #189AB4;
      text-decoration: none;
      font-weight: 500;
    }
    .footer-links a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-header">
      <img src="../assets/img/logo white.png" alt="HouseMaster Logo">
      <h3>Admin Portal</h3>
      <p class="text-muted">Please login to your account</p>
    </div>

    <?php if (!empty($error)) : ?>
      <div class="alert alert-danger text-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-floating mb-3">
        <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required autofocus>
        <label for="floatingEmail">Email Address</label>
      </div>
      <div class="form-floating mb-4">
        <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
        <label for="floatingPassword">Password</label>
      </div>
      
      <button type="submit" class="btn btn-primary w-100">
        Log In <i class="fas fa-sign-in-alt ms-2"></i>
      </button>

      <div class="footer-links">
        <p class="mb-1">Don't have an account? <a href="admin-register.php">Register here</a></p>
        <!-- <a href="admin-forgot-password.php" class="text-muted small">Forgot Password?</a> -->
      </div>
    </form>
  </div>
</body>
</html>
