<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HouseMaster - Boarding House Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #05445E;
            --secondary-color: #189AB4;
            --accent-color: #75E6DA;
            --light-bg: #f4f6f9;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 100px 0;
            text-align: center;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 50px;
            box-shadow: 0 10px 30px rgba(5, 68, 94, 0.2);
        }
        .hero-logo {
            max-width: 220px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
        }
        .role-card {
            border: none;
            border-radius: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            height: 100%;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
        }
        .role-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-custom:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: scale(1.05);
        }
        footer {
            margin-top: auto;
            padding: 40px 0;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <img src="assets/img/logo white.png" alt="HouseMaster Logo" class="hero-logo">
            <h1 class="display-5 fw-bold mb-3">Welcome to HouseMaster</h1>
            <p class="lead opacity-90">The smart, efficient way to manage boarding houses and dormitories.</p>
        </div>
    </div>

    <!-- Role Selection -->
    <div class="container">
        <div class="row justify-content-center g-4">
            <!-- Unified Login -->
            <div class="col-md-6 col-lg-5">
                <div class="card role-card p-4 text-center">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <h3 class="card-title fw-bold mb-3">Login to Portal</h3>
                        <p class="card-text text-muted mb-4">
                            Access your account. Whether you are a Landlord, Tenant, or Administrator, log in here.
                        </p>
                        <a href="login.php" class="btn btn-custom stretched-link px-5">Go to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container py-5 mt-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold" style="color: var(--primary-color);">Why Choose HouseMaster?</h2>
            <p class="text-muted lead">Everything you need to manage your property effectively.</p>
        </div>
        
        <div class="row g-4 text-center">
            <div class="col-md-6 col-lg-3">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 border border-light">
                    <div class="mb-3" style="color: var(--secondary-color);">
                        <i class="fas fa-file-invoice-dollar fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Automated Billing</h5>
                    <p class="text-muted small mb-0">Say goodbye to manual calculations. Generate monthly bills automatically and track payment statuses in real-time.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 border border-light">
                    <div class="mb-3" style="color: var(--secondary-color);">
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Tenant Management</h5>
                    <p class="text-muted small mb-0">Keep all tenant details, contracts, and emergency contacts organized in one secure, accessible place.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 border border-light">
                    <div class="mb-3" style="color: var(--secondary-color);">
                        <i class="fas fa-door-open fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Room & Inventory</h5>
                    <p class="text-muted small mb-0">Monitor room occupancy, manage capacity, and track furniture or inventory items assigned to each room.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 border border-light">
                    <div class="mb-3" style="color: var(--secondary-color);">
                        <i class="fas fa-comments fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Direct Communication</h5>
                    <p class="text-muted small mb-0">Post announcements for all tenants or chat directly with individuals to resolve issues quickly.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="mb-2">&copy; 2025 HouseMaster. All rights reserved.</p>
            <div class="small">
                <a href="superadmin/login.php" class="text-decoration-none text-muted opacity-50 hover-opacity-100">Super Admin Access</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>