<?php
session_start();

// Check if the super admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

$message = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $value = trim($value);
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $message = "<div class='alert alert-success alert-dismissible fade show'>Settings updated successfully.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// Fetch current settings
$settings_result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Settings - HouseMaster Super Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #05445E;
            --secondary-color: #189AB4;
            --accent-color: #75E6DA;
            --light-bg: #f4f6f9;    
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Poppins', sans-serif;
        }
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            z-index: 1;
            top: 0;
            left: 0;
            background-color: #05445E;
            overflow-x: hidden;
            padding-top: 20px;
        }
        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 1rem;
            color: rgba(255,255,255,0.8);
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar a.active {
            background-color: #189AB4;
            color: white;
        }
        .sidebar .logo-hold {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar .logo {
            width: 160px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            border-left: 10px #05445E solid;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #033245;
            border-color: #033245;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
        <?php echo $message; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-cogs me-2"></i>Global Payment Settings</h5>
                        <small class="text-muted">These details will be shown to owners when they make a payment.</small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <h6 class="text-primary fw-bold mb-3">GCash Details</h6>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">GCash Number</label>
                                <div class="col-sm-8"><input type="text" name="settings[gcash_number]" class="form-control" value="<?php echo htmlspecialchars($settings['gcash_number'] ?? ''); ?>"></div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Account Name</label>
                                <div class="col-sm-8"><input type="text" name="settings[gcash_name]" class="form-control" value="<?php echo htmlspecialchars($settings['gcash_name'] ?? ''); ?>"></div>
                            </div>

                            <hr class="my-4">

                            <h6 class="text-primary fw-bold mb-3">Bank Transfer Details</h6>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Bank Name</label>
                                <div class="col-sm-8"><input type="text" name="settings[bank_name]" class="form-control" value="<?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?>"></div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Account Number</label>
                                <div class="col-sm-8"><input type="text" name="settings[bank_account_num]" class="form-control" value="<?php echo htmlspecialchars($settings['bank_account_num'] ?? ''); ?>"></div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Account Name</label>
                                <div class="col-sm-8"><input type="text" name="settings[bank_account_name]" class="form-control" value="<?php echo htmlspecialchars($settings['bank_account_name'] ?? ''); ?>"></div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>