<?php
// This header file now handles session starting, config inclusion,
// and all account status enforcement (suspended/payment_due checks).
require_once 'header.php';

// Get the boarding code for the logged-in admin
$boarding_code = $_SESSION['boarding_code'];

$message = "";

// ✅ Fetch plan limits and current usage
$plan_stmt = $conn->prepare("SELECT p.max_rooms, p.name as plan_name FROM admins a JOIN subscription_plans p ON a.selected_plan_id = p.id WHERE a.boarding_code = ?");
$plan_stmt->bind_param("s", $boarding_code);
$plan_stmt->execute();
$plan_data = $plan_stmt->get_result()->fetch_assoc();
$max_rooms = $plan_data['max_rooms'] ?? 0; // 0 means unlimited
$plan_name = $plan_data['plan_name'] ?? 'Standard';

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM rooms WHERE boarding_code = ?");
$count_stmt->bind_param("s", $boarding_code);
$count_stmt->execute();
$current_rooms = $count_stmt->get_result()->fetch_assoc()['total'];

// Display session-based messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Check for post_max_size overflow (if the upload is too large)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES)) {
    $message = "Error: The uploaded data exceeds the post_max_size limit in your server configuration.";
}

// Handle Delete Room Image
if (isset($_POST['delete_room_image'])) {
    $image_id = $_POST['image_id'];

    // Security check: ensure admin owns the room of the image being deleted
    $verify_stmt = $conn->prepare("SELECT ri.image_filename FROM rooms r JOIN room_images ri ON r.id = ri.room_id WHERE ri.id = ? AND r.boarding_code = ?");
    $verify_stmt->bind_param("is", $image_id, $boarding_code);
    $verify_stmt->execute();
    $image_result = $verify_stmt->get_result();

    if ($image_result->num_rows > 0) {
        $image_file = $image_result->fetch_assoc();
        $filepath = "../assets/uploads/rooms/" . $image_file['image_filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $delete_stmt = $conn->prepare("DELETE FROM room_images WHERE id = ?");
        $delete_stmt->bind_param("i", $image_id);
        $delete_stmt->execute();
        $_SESSION['message'] = "<div class='alert alert-success'>Image deleted successfully.</div>";
        header("Location: rooms.php"); // Refresh page
        exit();
    }
}

// Add Room
if (isset($_POST['add_room']) && !in_array($account_status, ['pending', 'restricted'])) {
    // ✅ Check room limit before adding
    if ($max_rooms > 0 && $current_rooms >= $max_rooms) {
        $message = "<div class='alert alert-warning'><strong>Limit Reached:</strong> Your current plan ($plan_name) allows a maximum of $max_rooms rooms. Please upgrade your subscription to add more.</div>";
    } else {
    $room_label = $_POST['room_label'];
    $capacity    = $_POST['capacity'];
    $rental_rate = $_POST['rental_rate'];
    // Generate a unique code for the room
    $room_code = strtoupper(substr(md5(uniqid($room_label, true)), 0, 8));

    $stmt = $conn->prepare("INSERT INTO rooms (room_label, capacity, rental_rate, boarding_code, room_code) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sidss", $room_label, $capacity, $rental_rate, $boarding_code, $room_code);
    if ($stmt->execute()) {
        $room_id = $stmt->insert_id;

        // Handle multiple image uploads
        if (isset($_FILES['room_images'])) {
            $target_dir = "../assets/uploads/rooms/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $image_stmt = $conn->prepare("INSERT INTO room_images (room_id, image_filename) VALUES (?, ?)");

            $file_count = count($_FILES['room_images']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['room_images']['error'][$i] == UPLOAD_ERR_OK) {
                    $name = $_FILES['room_images']['name'][$i];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $new_filename = uniqid('room_', true) . "." . $ext;
                        if (move_uploaded_file($_FILES['room_images']['tmp_name'][$i], $target_dir . $new_filename)) {
                            $image_stmt->bind_param("is", $room_id, $new_filename);
                            $image_stmt->execute();
                        }
                    }
                } elseif ($_FILES['room_images']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    $message = "Error uploading file: Code " . $_FILES['room_images']['error'][$i];
                }
            }
        }
        $_SESSION['message'] = "<div class='alert alert-success'>Room added successfully.</div>";
        header("Location: rooms.php");
        exit();
    } else {
        $message = "Error: " . $conn->error;
    }
    }
}

// Handle Update Room
if (isset($_POST['update_room']) && !in_array($account_status, ['pending', 'restricted'])) {
    $room_id_to_update = $_POST['room_id'];
    $capacity = $_POST['capacity'];
    $rental_rate = $_POST['rental_rate'];

    // Security check: ensure admin can only edit their own rooms
    $update_stmt = $conn->prepare("UPDATE rooms SET capacity = ?, rental_rate = ? WHERE id = ? AND boarding_code = ?");
    $update_stmt->bind_param("idis", $capacity, $rental_rate, $room_id_to_update, $boarding_code);
    
    if ($update_stmt->execute()) {
        $upload_errors = [];
        // Handle adding new images
        if (isset($_FILES['new_room_images'])) {
            $target_dir = "../assets/uploads/rooms/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $image_stmt = $conn->prepare("INSERT INTO room_images (room_id, image_filename) VALUES (?, ?)");
            
            $files = $_FILES['new_room_images'];
            // Check if files were actually uploaded
            if (is_array($files['name'])) {
                $file_count = count($files['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    $name = $files['name'][$i];
                    $error = $files['error'][$i];
                    
                    if ($error == UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $new_filename = uniqid('room_', true) . "." . $ext;
                            if (move_uploaded_file($files['tmp_name'][$i], $target_dir . $new_filename)) {
                                $image_stmt->bind_param("is", $room_id_to_update, $new_filename);
                                $image_stmt->execute();
                            } else {
                                $upload_errors[] = "Failed to move file: $name";
                            }
                        } else {
                            $upload_errors[] = "Invalid file type: $name";
                        }
                    } elseif ($error != UPLOAD_ERR_NO_FILE) {
                        $upload_errors[] = "Error uploading $name (Code: $error)";
                    }
                }
            }
        }

        if (empty($upload_errors)) {
            $_SESSION['message'] = "<div class='alert alert-success'>Room updated successfully.</div>";
            header("Location: rooms.php");
            exit();
        } else {
            $message = "<div class='alert alert-warning'>Room updated, but some images failed: " . implode(", ", $upload_errors) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Error updating room: " . $conn->error . "</div>";
    }
}

// Fetch tenants & rooms
$all_rooms_stmt = $conn->prepare("
    SELECT r.*, 
           (SELECT COUNT(tr.id) FROM tenant_rooms tr JOIN tenants t ON tr.tenant_id = t.id WHERE tr.room_id = r.id AND t.status = 'active') AS tenants
    FROM rooms r
    WHERE r.boarding_code = ? 
    GROUP BY r.id 
    ORDER BY r.created_at DESC");
$all_rooms_stmt->bind_param("s", $boarding_code);
$all_rooms_stmt->execute();
$all_rooms = $all_rooms_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Room Management — HouseMaster</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="side.css">

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
<style>
    :root {
        --primary-color: #05445E;
        --secondary-color: #189AB4;
        --accent-color: #75E6DA;
        --light-bg: #f4f6f9;
    }
    body {
        background-color: var(--light-bg);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .btn-link

    {
    --bs-btn-font-weight: 400;
    --bs-btn-color: #05445E;
    --bs-btn-bg: transparent;
    --bs-btn-border-color: transparent;
    --bs-btn-hover-color: #05445E;
    --bs-btn-hover-border-color: transparent;
    --bs-btn-active-color: #05445E;
    --bs-btn-active-border-color: transparent;
    --bs-btn-disabled-color: #6c757d;
    --bs-btn-disabled-border-color: transparent;
    --bs-btn-box-shadow: 0 0 0 #000;
    --bs-btn-focus-shadow-rgb: 49, 132, 253;
    text-decoration: underline;
    }
    .main { padding: 2rem; }
    .page-header { margin-bottom: 2rem; }
    .page-title { color: var(--primary-color); font-weight: 800; font-size: 1.75rem; margin-bottom: 0.5rem; }
    .page-subtitle { color: #6c757d; font-size: 0.95rem; }

    /* Content Cards */
    .content-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        background: #fff;   
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .active>.page-link {
        background-color: #05445E !important;
    }
    .dropdown-menu {
            --bs-dropdown-link-active-bg: #05445E;
    }
    .dropdown-item:hover {
        background-color: #16b658 !important; /* Manually edit hover color here */
    }
    .content-card-header {
        background: #fff;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-title-text { font-weight: 700; color: var(--primary-color); font-size: 1.1rem; margin: 0; }
    .card-body { padding: 2rem; }

    /* Form Elements */
    .form-label { font-weight: 600; font-size: 0.85rem; color: #525f7f; margin-bottom: 0.4rem; }
    .form-control, .form-select {
        border: 1px solid #e0e6ed; border-radius: 8px; padding: 0.6rem 1rem;
        font-size: 0.95rem; color: #32325d; transition: all 0.2s;
    }
    .form-select:hover {
        background-color: #e9ecef !important; /* Manually edit hover color here */
        cursor: pointer;
    }
    .form-control:focus, .form-select:focus { border-color: var(--secondary-color); box-shadow: 0 0 0 3px rgba(24, 154, 180, 0.1); }
    .form-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--primary-color);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 1.5rem 0 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    /* Table Styling */
    .table thead th {
        background-color: #f8f9fa; color: #8898aa; font-weight: 600; font-size: 0.8rem;
        text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e9ecef; padding: 1rem 1.5rem;
    }
    .table tbody td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f0f0f0; color: #525f7f; font-size: 0.95rem; }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover { background-color: #fcfcfc; }
    
    /* Icons & Avatars */
    .icon-circle {
        width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .bg-blue-soft { background-color: rgba(5, 68, 94, 0.1); color: #05445E; }

    /* Progress Bar */
    .occupancy-wrapper { min-width: 140px; }
    .progress { height: 6px; border-radius: 10px; background-color: #edf2f9; margin-top: 8px; }
    .progress-bar { border-radius: 10px; }
    
    /* Action Buttons */
    .btn-icon {
        width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; transition: all 0.2s; margin-right: 5px; border: none;
    }
    .btn-icon:hover { transform: translateY(-2px); }
    .btn-view { color: var(--primary-color); background: rgba(5, 68, 94, 0.1); }
    .btn-view:hover { background: var(--primary-color); color: #fff; }
    .btn-edit { color: #17a2b8; }
    .btn-edit:hover { background: var(--primary-color); color: #fff; }
    .btn-manage { color: #6c757d; background: rgba(108, 117, 125, 0.1); }
    .btn-manage:hover { background: #6c757d; color: #fff; }

    /* Modals */
    .modal-content { border: none; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    .modal-header { border-bottom: 1px solid #f0f0f0; padding: 1.5rem 2rem; background: #fff; border-radius: 16px 16px 0 0; }
    .modal-title { font-weight: 700; color: var(--primary-color); font-size: 1.25rem; }
    .modal-body { padding: 2rem; }
    .modal-footer { background-color: #f8f9fa; border-top: 1px solid #f0f0f0; padding: 1.25rem 2rem; border-radius: 0 0 16px 16px; }
    
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover { background-color: #032f40; border-color: #032f40; }
    .btn-success { background-color: #189AB4; border-color: #189AB4; color: #fff; }
    .btn-success:hover { background-color: #107484; border-color: #107484; color: #fff; }
</style>
</head>
<body>
<?php include("navbar.php"); ?>
<div class="main">
    <div class="page-header">
        <h3 class="page-title">Room Management</h3>
        <p class="page-subtitle">Create and manage rooms, track occupancy, and set house rules.</p>
    </div>

    <?php if (!empty($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>
    

    <div class="row">
        <div class="col-12">
    <?php if (!in_array($account_status, ['pending', 'restricted'])): ?>
    <!-- Add Room -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="card-title-text"><i class="fas fa-plus-circle me-2"></i>Add New Room</h5>
                <?php if ($max_rooms > 0): ?>
                    <span class="badge bg-<?php echo ($current_rooms >= $max_rooms) ? 'danger' : 'info'; ?>">
                        <?php echo $current_rooms . ' / ' . $max_rooms; ?> Rooms Used
                    </span>
                <?php else: ?>
                    <span class="badge bg-success">Unlimited Rooms</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($max_rooms > 0 && $current_rooms >= $max_rooms): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-lock me-2"></i> You have reached the room limit for the <strong><?php echo htmlspecialchars($plan_name); ?></strong> plan.
                    </div>
                <?php else: ?>
                <form method="POST" action="rooms.php" enctype="multipart/form-data">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label>Room Label</label>
                            <input type="text" name="room_label" class="form-control" placeholder="e.g. Dorm A - Room 1" required>
                        </div>
                        <div class="col-md-4">
                            <label>Capacity</label>
                            <input type="number" name="capacity" class="form-control" min="1" placeholder="Number of beds" required>
                        </div>
                        <div class="col-md-4">
                            <label>Rental Rate (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">₱</span>
                                <input type="number" step="0.01" name="rental_rate" class="form-control border-start-0 ps-0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label>Room Images (Optional)</label>
                            <div class="p-3 bg-light rounded-3 border border-dashed text-center">
                                <div id="add_room_images_container">
                                    <input type="file" name="room_images[]" class="form-control" accept="image/*" multiple>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none mt-2" onclick="addFileInput('add_room_images_container', 'room_images[]')">
                                    <i class="fas fa-plus me-1"></i> Add Another Image
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" name="add_room" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-2"></i> Create Room</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
        </div>
    </div>

    <!-- All Rooms -->
    <div class="content-card">
        <div class="content-card-header">
            <h5 class="card-title-text">Room List</h5>
            <div class="d-flex align-items-center">
                <div class="d-flex align-items-center me-2">
                    <label for="sortControl" class="form-label mb-0 me-2 small text-muted">Sort:</label>
                    <select id="sortControl" class="form-select form-select-sm border-0 bg-light" style="width: auto; font-weight: 600;">
                            <option value="0-asc">Room Label (A-Z)</option>
                            <option value="0-desc">Room Label (Z-A)</option>
                            <option value="1-desc">Occupancy (High to Low)</option>
                            <option value="1-asc">Occupancy (Low to High)</option>
                        </select>
                    </div>
                </div>
        </div>
        <div class="table-responsive">
            <table id="roomsTable" class="table mb-0">
                <thead>
                        <tr>
                            <th>Room Details</th>
                            <th>Occupancy</th>
                        <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while ($row = $all_rooms->fetch_assoc()): ?>
                            <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-blue-soft me-3">
                                        <i class="fas fa-door-open"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['room_label']); ?></div>
                                        <div class="small text-muted">Rate: ₱<?php echo number_format($row['rental_rate'], 2); ?></div>
                                    </div>
                                </div>
                            </td>
                                <?php
                                    $tenants = (int)$row['tenants'];
                                    $capacity = (int)$row['capacity'];
                                    $occupancy_percentage = ($capacity > 0) ? ($tenants / $capacity) * 100 : 0;
                                    $progress_color = 'bg-success'; // Green for available
                                    if ($occupancy_percentage >= 90) {
                                        $progress_color = 'bg-danger'; // Red for near/full
                                    } elseif ($occupancy_percentage >= 50) {
                                        $progress_color = 'bg-warning'; // Yellow for half-full
                                    }
                                ?>
                                <td data-sort="<?php echo $occupancy_percentage; ?>">
                                <div class="occupancy-wrapper">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-bold <?php echo str_replace('bg-', 'text-', $progress_color); ?>"><?php echo $tenants; ?> / <?php echo $capacity; ?></span>
                                        <span class="text-muted"><?php echo round($occupancy_percentage); ?>%</span>
                                    </div>
                                    <div class="progress">
                                            <div class="progress-bar <?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo $occupancy_percentage; ?>%;" aria-valuenow="<?php echo $occupancy_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </td>
                            <td class="text-end">
                                <button class="btn-icon btn-view" title="View Details" data-bs-toggle="modal" data-bs-target="#roomDetailsModal<?php echo $row['id']; ?>"><i class="fas fa-eye"></i></button>
                                        <?php if (!in_array($account_status, ['pending', 'restricted'])): ?>
                                <button class="btn-icon btn-edit" title="Edit Room" data-bs-toggle="modal" data-bs-target="#editRoomModal<?php echo $row['id']; ?>"><i class="fas fa-edit"></i></button>
                                <div class="d-inline-block dropdown">
                                    <button type="button" class="btn-icon btn-manage dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Manage Room"><i class="fas fa-cog"></i></button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="openModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['room_label'])); ?>', 'item')"><i class="fas fa-plus-circle fa-fw me-2"></i>Manage Items</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="openModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['room_label'])); ?>', 'rule')"><i class="fas fa-gavel fa-fw me-2"></i>Manage Rules</a></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>

                            <!-- Room Details Modal -->
                            <div class="modal fade" id="roomDetailsModal<?php echo $row['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title mb-0"><?php echo htmlspecialchars($row['room_label']); ?></h5>
                                        </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                        <div class="row g-4">
                                            <div class="col-md-7">
                                                <h6 class="form-section-title">Gallery</h6>
                                            <?php
                                            $images_stmt = $conn->prepare("SELECT id, image_filename FROM room_images WHERE room_id = ?");
                                            $images_stmt->bind_param("i", $row['id']);
                                            $images_stmt->execute();
                                            $images_list = $images_stmt->get_result();
                                            if ($images_list->num_rows > 0) {
                                                    echo '<div class="d-flex flex-wrap gap-2">';
                                                while ($img = $images_list->fetch_assoc()) {
                                                        echo '<img src="../assets/uploads/rooms/' . htmlspecialchars($img['image_filename']) . '" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">';
                                                }
                                                echo '</div>';
                                            } else {
                                                    echo '<div class="p-4 bg-light rounded text-center text-muted"><i class="fas fa-image fa-2x mb-2 opacity-25"></i><br>No images uploaded</div>';
                                            }
                                            ?>
                                            
                                                <h6 class="form-section-title mt-4">Assigned Tenants</h6>
                                            <?php
                                            $tenant_list_stmt = $conn->prepare("SELECT t.id, t.fullname FROM tenants t JOIN tenant_rooms tr ON t.id = tr.tenant_id WHERE tr.room_id = ?");
                                            $tenant_list_stmt->bind_param("i", $row['id']);
                                            $tenant_list_stmt->execute();
                                            $tenant_list = $tenant_list_stmt->get_result();
                                            if ($tenant_list->num_rows > 0) {
                                                    echo '<ul class="list-group list-group-flush border rounded">';
                                                while ($t = $tenant_list->fetch_assoc()) {
                                                        echo '<li class="list-group-item bg-light"><i class="fas fa-user me-2 text-muted"></i>' . htmlspecialchars($t['fullname']) . '</li>';
                                                    echo '</li>';
                                                }
                                                echo '</ul>';
                                            } else {
                                                    echo '<p class="text-muted small fst-italic">No tenants assigned.</p>';
                                            }
                                            ?>
                                            </div>
                                            <div class="col-md-5">
                                                <div class="p-3 bg-light rounded mb-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted">Capacity</span>
                                                        <span class="fw-bold"><?php echo $row['capacity']; ?> Persons</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Rate</span>
                                                        <span class="fw-bold text-primary">₱<?php echo number_format($row['rental_rate'], 2); ?></span>
                                                    </div>
                                                </div>

                                                <h6 class="form-section-title">Inclusions</h6>
                                                    <?php
                                                    $items_stmt = $conn->prepare("SELECT item_name, quantity, `condition` FROM room_items WHERE room_id = ? ORDER BY created_at ASC");
                                                    $items_stmt->bind_param("i", $row['id']);
                                                    $items_stmt->execute();
                                                    $items_list = $items_stmt->get_result();
                                                    if ($items_list->num_rows > 0) {
                                                    echo '<ul class="list-group list-group-flush small">';
                                                        while ($item = $items_list->fetch_assoc()) {
                                                        echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0">';
                                                            echo htmlspecialchars($item['item_name']);
                                                            echo '<div><span class="badge bg-secondary">x' . htmlspecialchars($item['quantity']) . '</span> <span class="badge bg-info text-dark">' . htmlspecialchars($item['condition']) . '</span></div>';
                                                            echo '</li>';
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                    echo '<p class="text-muted small">No items.</p>';
                                                    }
                                                    ?>

                                                <h6 class="form-section-title mt-3">Rules</h6>
                                                    <?php
                                                    $rules_stmt = $conn->prepare("SELECT rule_text FROM room_rules WHERE room_id = ? AND type = 'rule' ORDER BY created_at ASC");
                                                    $rules_stmt->bind_param("i", $row['id']);
                                                    $rules_stmt->execute();
                                                    $rules_list = $rules_stmt->get_result();
                                                    if ($rules_list->num_rows > 0) {
                                                    echo '<ul class="list-group list-group-flush small">';
                                                        while ($rule = $rules_list->fetch_assoc()) {
                                                        echo '<li class="list-group-item px-0"><i class="fas fa-angle-right me-2 text-warning"></i>' . htmlspecialchars(trim($rule['rule_text'])) . '</li>';
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                    echo '<p class="text-muted small">No rules.</p>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Room Modal -->
                            <div class="modal fade" id="editRoomModal<?php echo $row['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Room: <?php echo htmlspecialchars($row['room_label']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="rooms.php" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Capacity</label>
                                                        <input type="number" name="capacity" class="form-control" min="1" value="<?php echo $row['capacity']; ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Rental Rate (₱)</label>
                                                        <input type="number" step="0.01" name="rental_rate" class="form-control" value="<?php echo $row['rental_rate']; ?>" required>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label">Current Images</label>
                                                        <div class="d-flex flex-wrap gap-2 p-3 bg-light rounded">
                                                            <?php
                                                            $edit_images_stmt = $conn->prepare("SELECT id, image_filename FROM room_images WHERE room_id = ?");
                                                            $edit_images_stmt->bind_param("i", $row['id']);
                                                            $edit_images_stmt->execute();
                                                            $edit_images_list = $edit_images_stmt->get_result();
                                                            if ($edit_images_list->num_rows > 0) {
                                                                while($img = $edit_images_list->fetch_assoc()) {
                                                                    $image_path = '../assets/uploads/rooms/' . htmlspecialchars($img['image_filename']);
                                                                    echo '<div class="position-relative d-inline-block">';
                                                                    echo '<a href="' . $image_path . '" title="View full image" target="_blank">';
                                                                    echo '<img src="' . $image_path . '" width="80" height="80" class="rounded" style="object-fit: cover; cursor: pointer;">';
                                                                    echo '</a>';
                                                                    echo '<button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" style="line-height:1;padding:2px 5px;" onclick="if(confirm(\'Delete this image?\')){ document.getElementById(\'deleteImageForm' . $img['id'] . '\').submit(); }"><i class="fas fa-times"></i></button>';
                                                                    echo '</div>';
                                                                }
                                                            } else {
                                                                echo '<p class="text-muted small">No images yet.</p>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label">Add More Images</label>
                                                        <div id="edit_room_images_container_<?php echo $row['id']; ?>">
                                                            <input type="file" name="new_room_images[]" class="form-control" accept="image/*" multiple>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addFileInput('edit_room_images_container_<?php echo $row['id']; ?>', 'new_room_images[]')">
                                                            <i class="fas fa-plus"></i> Add Another Image
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="update_room" class="btn btn-primary px-4">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php
                            if (isset($edit_images_list) && $edit_images_list && $edit_images_list->num_rows > 0) {
                                $edit_images_list->data_seek(0); // Reset pointer
                                while($img = $edit_images_list->fetch_assoc()):
                            ?>
                            <form method="POST" action="rooms.php" id="deleteImageForm<?php echo $img['id']; ?>" class="d-none">
                                <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                <input type="hidden" name="delete_room_image" value="1">
                            </form>
                            <?php 
                                endwhile; 
                            } 
                            ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
    <footer class="mt-5 text-center text-muted small">HouseMaster © 2025 — Boarding House & Dormitory Management System</footer>

<!-- Modal -->
<div class="modal fade" id="rulesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rulesModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="rulesContent"></div>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#roomsTable').DataTable({
        "lengthChange": false,
        "info": false,
        "ordering": true, // Keep ordering enabled for the API
        "columnDefs": [
            { "orderable": false, "targets": "_all" } // But disable header-click sorting UI
        ]
    });

    $('#sortControl').on('change', function() {
        var val = $(this).val();
        var parts = val.split('-');
        var colIndex = parseInt(parts[0], 10);
        table.order([colIndex, parts[1]]).draw();
    });
});

function addFileInput(containerId, inputName) {
    var container = document.getElementById(containerId);
    var div = document.createElement('div');
    div.className = 'input-group mt-2';
    div.innerHTML = `
        <input type="file" name="${inputName}" class="form-control" accept="image/*" multiple>
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}
// Open Room Rules Modal
function openModal(roomId, roomLabel, type) {
    const typeTitle = type.charAt(0).toUpperCase() + type.slice(1); // Capitalize
    document.getElementById("rulesModalLabel").textContent = `Manage ${typeTitle}s for ${roomLabel}`;
    loadModalContent(roomId, type);
}

function loadModalContent(roomId, type) {
    fetch(`room_rules.php?room_id=${roomId}&type=${type}`)
        .then(res => res.text())
        .then(html => {
            const modalBody = document.getElementById("rulesContent");
            modalBody.innerHTML = html;
            attachModalHandlers(roomId, type);

            let rulesModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("rulesModal"));
            rulesModal.show();
        })
        .catch(err => console.error(err));
}

// Attach event listeners after modal content loads
function attachModalHandlers(roomId, type) {
    const modalBody = document.getElementById("rulesContent");

    // Add rule
    const addForm = modalBody.querySelector('form'); // Generalize to find any form
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('add_item_or_rule', '1');
            fetch(`room_rules.php?room_id=${roomId}&type=${type}`, {
                method: "POST",
                body: formData
            })
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
                attachModalHandlers(roomId, type); // Reattach listeners
            })
            .catch(err => console.error(err));
        });
    }

    // Delete rule
    modalBody.querySelectorAll('.delete-item-or-rule').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm(`Are you sure you want to delete this ${type}?`)) return;
            const formData = new FormData();
            formData.append('delete_item_or_rule', '1');
            formData.append('item_id', this.dataset.id);
            fetch(`room_rules.php?room_id=${roomId}&type=${type}`, {
                method: "POST",
                body: formData
            })
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
                attachModalHandlers(roomId, type); // Reattach listeners
            })
            .catch(err => console.error(err));
        });
    });
}


</script>
</body>
</html>
