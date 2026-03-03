<?php
session_start();
include __DIR__ . "/../config.php";

$error = ""; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "An admin with this email already exists.";
        } else {
            
            do {
                $boarding_code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $code_check_stmt = $conn->prepare("SELECT id FROM admins WHERE boarding_code = ?");
                $code_check_stmt->bind_param("s", $boarding_code);
                $code_check_stmt->execute();
                $code_result = $code_check_stmt->get_result();
            } while ($code_result->num_rows > 0);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = $conn->prepare("INSERT INTO admins (name, email, password, boarding_code) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $name, $email, $hashed_password, $boarding_code);

            if ($insert_stmt->execute()) {
               
                $_SESSION['admin_id'] = $insert_stmt->insert_id;
                $_SESSION['admin_name'] = $name;
                $_SESSION['boarding_code'] = $boarding_code;
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Admin Registration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      background: white;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .register-card {
      width: 100%;
      max-width: 500px;
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
    .register-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .register-header img {
      width: 150px;
      margin-bottom: 15px;
    }
    .register-header h3 {
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
  <div class="register-card">
    <div class="register-header">
      <img src="../assets/img/logo white.png " alt="HouseMaster Logo">
      <h3>Create Account</h3>
      <p class="text-muted">Join us to manage your boarding house</p>
    </div>

    <?php if (!empty($error)) : ?>
      <div class="alert alert-danger text-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-floating mb-3">
        <input type="text" name="name" class="form-control" id="floatingName" placeholder="Full Name" required>
        <label for="floatingName">Full Name</label>
      </div>
      <div class="form-floating mb-3">
        <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required>
        <label for="floatingEmail">Email Address</label>
      </div>
      <div class="form-floating mb-3">
        <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
        <label for="floatingPassword">Password</label>
      </div>
      <div class="form-floating mb-4">
        <input type="password" name="confirm_password" class="form-control" id="floatingConfirmPassword" placeholder="Confirm Password" required>
        <label for="floatingConfirmPassword">Confirm Password</label>
      </div>
      
      <button type="submit" class="btn btn-primary w-100">
        Register <i class="fas fa-user-plus ms-2"></i>
      </button>

      <div class="footer-links">
        <p class="mb-0">Already have an account? <a href="../login.php">Log In</a></p>
      </div>
    </form>
  </div>
</body>
</html>