<?php
session_start();

// Check if the super admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

$message = "";

// Handle Add Plan
if (isset($_POST['add_plan'])) {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);
    $max_rooms = intval($_POST['max_rooms']);
    $description = trim($_POST['description']);
    $features = trim($_POST['features']);

    if (!empty($name) && $price >= 0 && $duration > 0) {
        $stmt = $conn->prepare("INSERT INTO subscription_plans (name, price, max_rooms, duration_days, description, features) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiiss", $name, $price, $max_rooms, $duration, $description, $features);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show'>Plan added successfully.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>Error adding plan: " . $conn->error . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    } else {
        $message = "<div class='alert alert-warning alert-dismissible fade show'>Please fill in all required fields correctly.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Handle Delete Plan
if (isset($_POST['delete_plan'])) {
    $id = intval($_POST['plan_id']);
    $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success alert-dismissible fade show'>Plan deleted successfully.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>Error deleting plan.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Fetch Plans
$plans = $conn->query("SELECT * FROM subscription_plans ORDER BY price ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - HouseMaster Super Admin</title>
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

        <div class="row">
            <!-- Add Plan Form -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0 fw-bold">Add New Plan</h5></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3"><label class="form-label">Plan Name</label><input type="text" name="name" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Price (₱)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Max Rooms</label><input type="number" name="max_rooms" class="form-control" value="0" required><div class="form-text">Enter 0 for unlimited rooms.</div></div>
                            <div class="mb-3"><label class="form-label">Duration (Days)</label><input type="number" name="duration" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                            <div class="mb-3"><label class="form-label">Features</label><textarea name="features" class="form-control" rows="3" placeholder="List features here..."></textarea></div>
                            <button type="submit" name="add_plan" class="btn btn-primary w-100">Create Plan</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Plans List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0 fw-bold">Existing Plans</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light"><tr><th>Name</th><th>Price</th><th>Max Rooms</th><th>Duration</th><th>Description</th><th>Features</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while ($row = $plans->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>₱<?php echo number_format($row['price'], 2); ?></td>
                                        <td><?php echo $row['max_rooms'] == 0 ? '<span class="badge bg-success">Unlimited</span>' : $row['max_rooms']; ?></td>
                                        <td><?php echo $row['duration_days']; ?> Days</td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($row['description']); ?></small></td>
                                        <td><small class="text-muted"><?php echo nl2br(htmlspecialchars($row['features'] ?? '')); ?></small></td>
                                        <td><form method="POST" onsubmit="return confirm('Delete this plan?');"><input type="hidden" name="plan_id" value="<?php echo $row['id']; ?>"><button type="submit" name="delete_plan" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>