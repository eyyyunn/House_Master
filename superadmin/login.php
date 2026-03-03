<?php
session_start();
require_once '../config.php';

// If admin is already logged in, redirect to their dashboard
if (isset($_SESSION['super_admin_id'])) {
    header("Location: index.php");
    exit();
}

$error_message = '';

// Check for logout reason
if (isset($_GET['reason']) && $_GET['reason'] === 'suspended') {
    $error_message = "Your account has been suspended. Please contact the administrator.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Username/Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM super_admins WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['password'])) {
                $_SESSION['super_admin_id'] = $admin['id'];

                header("Location: index.php");
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - HouseMaster</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="../assets/img/logo white.png" alt="HouseMaster Logo">
            <h3>Super Admin Portal</h3>
            <p class="text-muted">Please login to manage the system</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger text-center" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required autofocus>
                <label for="username">Username or Email</label>
            </div>
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                Sign In <i class="fas fa-sign-in-alt ms-2"></i>
            </button>
        </form>
    </div>
</body>
</html>