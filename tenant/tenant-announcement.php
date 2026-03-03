<?php
session_start();
include __DIR__ . "/../config.php";

// Require tenant login
if (!isset($_SESSION["tenant_id"])) {
    header("Location: tenant-auth.php");
    exit();
}
$tenant_id = $_SESSION["tenant_id"];
$boarding_code_stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
$boarding_code_stmt->bind_param("i", $tenant_id);
$boarding_code_stmt->execute();
$tenant_data = $boarding_code_stmt->get_result()->fetch_assoc();
$boarding_code = $tenant_data ? $tenant_data['boarding_code'] : null;

// ✅ Fetch all notices
$result = null; // Initialize result to null
if (!empty($boarding_code)) { // Only fetch notices if boarding_code is valid
    $sql_notices = "SELECT * FROM notices WHERE boarding_code = ? ORDER BY created_at DESC";
    $stmt_notices = $conn->prepare($sql_notices);
    $stmt_notices->bind_param("s", $boarding_code);
    $stmt_notices->execute();
    $result = $stmt_notices->get_result();
} else {
    error_log("Tenant ID: " . $tenant_id . " has an empty or NULL boarding_code. No announcements fetched.");
}

// ✅ Separate the latest notice and the past ones
$latestNotice = null;
$pastNotices = [];
if ($result && $result->num_rows > 0) { // Check if $result is not null and has rows (after potential debugging output)
    $latestNotice = $result->fetch_assoc(); // first row is latest
    while ($row = $result->fetch_assoc()) {
        $pastNotices[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements — HouseMaster</title>
    <link rel="stylesheet" href="tenant.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .main-container { max-width: 1000px; margin: 0 auto; padding: 30px 15px; }
        .page-title { color: #05445E; font-weight: 800; margin-bottom: 2rem; text-align: center; }
        
        .latest-card {
            border-left: 10px solid #05445E;
            color: #2c3e50;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(5, 68, 94, 0.2);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        .latest-card::before {
            content: '\f0a1'; /* fa-bullhorn */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            
            font-size: 2rem;
           
        }
        .latest-badge {
            background-color: #ffc107;
            color: #000;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .section-header {
            color: #05445E;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }
        .section-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dee2e6;
            margin-left: 15px;
        }
        
        .past-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
        }
        .past-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .past-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            display: block;
        }
        .past-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .past-body {
            color: #6c757d;
            font-size: 0.95rem;
            flex-grow: 1;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .btn-read-more {
            color: #189AB4;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            align-self: flex-start;
        }
        .btn-read-more:hover {
            color: #05445E;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<?php include("navbar.php"); ?>

<div class="main-container">
    <h3 class="page-title"> Announcements</h3>

    <?php if ($latestNotice): ?>
        <!-- Latest announcement board -->
        <div class="latest-card">
            <div class="d-flex align-items-center mb-3">
                <span class="badge bg-white text-primary fw-bold me-2">LATEST</span>
                <span class="text-white-50 small"><i class="far fa-clock me-1"></i> <?= date("F j, Y, g:i a", strtotime($latestNotice['created_at'])) ?></span>
            </div>
            <h2 class="fw-bold mb-3"><?= htmlspecialchars($latestNotice['title']) ?></h2>
            <p class="mb-0" style="font-size: 1.1rem; opacity: 0.95; line-height: 1.6;"><?= nl2br(htmlspecialchars($latestNotice['body'])) ?></p>
        </div>

        <!-- Past announcements -->
        <?php if (!empty($pastNotices)): ?>
            <div class="section-header">Past Announcements</div>
            <div class="row g-4">
                <?php foreach ($pastNotices as $row): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="past-card">
                            <span class="past-date"><i class="far fa-calendar-alt me-1"></i> <?= date("M d, Y", strtotime($row['created_at'])) ?></span>
                            <h5 class="past-title"><?= htmlspecialchars($row['title']) ?></h5>
                            <div class="past-body">
                                <?= nl2br(htmlspecialchars($row['body'])) ?>
                            </div>
                            <button class="btn btn-link btn-read-more p-0" data-bs-toggle="modal" data-bs-target="#noticeModal<?= $row['id'] ?>">Read More <i class="fas fa-arrow-right ms-1"></i></button>
                        </div>
                    </div>
                    
                    <!-- Modal for this notice -->
                    <div class="modal fade" id="noticeModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold"><?= htmlspecialchars($row['title']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small mb-3 border-bottom pb-2"><i class="far fa-clock me-1"></i> Posted on <?= date("F j, Y, g:i a", strtotime($row['created_at'])) ?></p>
                                    <div class="notice-content" style="white-space: pre-wrap;"><?= htmlspecialchars($row['body']) ?></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-bullhorn fa-3x mb-3 opacity-25"></i>
            <p>No announcements yet.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
