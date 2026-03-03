<?php
// This header file now handles session starting, config inclusion,
// and all account status enforcement (suspended/payment_due checks).
require_once 'header.php';

$admin_id = $_SESSION['admin_id'];
$message = "";
$boarding_code = $_SESSION['boarding_code'];

// Display session-based messages and set flag for broadcasting
if (isset($_SESSION['message'])) { // This block is hit on redirect after an action
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
    // This page was loaded after an action. The timestamp is already set by the action script.
}

// ✅ Fetch tenant status counts for the summary cards
$counts_stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM tenants WHERE admin_id = ? GROUP BY status");
$counts_stmt->bind_param("i", $admin_id);
$counts_stmt->execute();
$counts_result = $counts_stmt->get_result();

$status_counts = [
    'active' => 0,
    'pending' => 0,
    'inactive' => 0,
    'unassigned' => 0
];
$total_tenants = 0;

while ($row_count = $counts_result->fetch_assoc()) {
    if (isset($status_counts[$row_count['status']])) {
        $status_counts[$row_count['status']] = $row_count['count'];
    }
    $total_tenants += $row_count['count'];
}

// Handle Generate Bill
if (isset($_POST['generate_bill']) && !in_array($account_status, ['pending', 'restricted'])) {
    $tenant_id = $_POST['tenant_id'];
    $rental_rate = $_POST['rental_rate'];

    if (empty($rental_rate)) {
        $message = "<div class='alert alert-danger'>Cannot generate bill. Tenant is not assigned to a room with a rental rate.</div>";
    } else {
        // Find the last due date for this tenant, or use today if none exists
        $last_due_stmt = $conn->prepare("SELECT MAX(due_date) as last_due FROM payments WHERE tenant_id = ?");
        $last_due_stmt->bind_param("i", $tenant_id);
        $last_due_stmt->execute();
        $last_due_date = $last_due_stmt->get_result()->fetch_assoc()['last_due'];

        $next_due_date = $last_due_date ? date('Y-m-d', strtotime($last_due_date . ' +1 month')) : date('Y-m-d', strtotime('+1 month'));

        // Insert the new payment record
        $insert_stmt = $conn->prepare("INSERT INTO payments (tenant_id, admin_id, boarding_code, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insert_stmt->bind_param("iisds", $tenant_id, $admin_id, $boarding_code, $rental_rate, $next_due_date);
        $insert_stmt->execute();
        $conn->query("UPDATE system_state SET state_value = UNIX_TIMESTAMP() WHERE state_key = 'last_update_timestamp'");
        $_SESSION['message'] = "<div class='alert alert-success'>Bill generated successfully.</div>";
        header("Location: tenants.php" . (!empty($_GET['status']) ? "?status=" . $_GET['status'] : ""));
        exit();
    }
}

// Handle Approve Tenant & Generate First Bill
if (isset($_POST['approve_and_bill']) && !in_array($account_status, ['pending', 'restricted'])) {
    $tenant_id_to_approve = $_POST['tenant_id'];
    $start_date = $_POST['start_date'];
    $rental_rate = $_POST['rental_rate'];
    $start_boarding_date = $_POST['start_date']; // Use start_date for the tenant's record
    $requested_room_id = $_POST['requested_room_id'];

    if (empty($start_date) || empty($rental_rate)) {
        $message = "<div class='alert alert-danger'>Start date and rental rate are required to approve and bill a tenant.</div>";
    } else {
        // 1. Update tenant status to active and set start_boarding_date
        $approve_stmt = $conn->prepare("UPDATE tenants SET status = 'active', start_boarding_date = ? WHERE id = ? AND admin_id = ? AND status = 'pending'");
        $approve_stmt->bind_param("sii", $start_boarding_date, $tenant_id_to_approve, $admin_id);
        $approve_stmt->execute();

        // 2. Assign tenant to the requested room
        if (!empty($requested_room_id)) {
            $assign_stmt = $conn->prepare("INSERT INTO tenant_rooms (tenant_id, room_id) VALUES (?, ?)");
            $assign_stmt->bind_param("ii", $tenant_id_to_approve, $requested_room_id);
            $assign_stmt->execute();
        }

        // 3. Calculate the due date for the next month
        $due_date = date('Y-m-d', strtotime($start_date . ' +1 month'));

        // 4. Insert the first payment record with the calculated due date
        $insert_bill_stmt = $conn->prepare("INSERT INTO payments (tenant_id, admin_id, boarding_code, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insert_bill_stmt->bind_param("iisds", $tenant_id_to_approve, $admin_id, $boarding_code, $rental_rate, $due_date);
        $insert_bill_stmt->execute();

        $conn->query("UPDATE system_state SET state_value = UNIX_TIMESTAMP() WHERE state_key = 'last_update_timestamp'");
        $_SESSION['message'] = "<div class='alert alert-success'>Tenant approved and first bill generated.</div>";
        // Redirect to clear POST data and show the updated list, preserving the filter
        header("Location: tenants.php?status=active");
        exit();
    }
}

// Handle Deny Tenant
if (isset($_POST['deny_tenant']) && !in_array($account_status, ['pending', 'restricted'])) {
    $tenant_id_to_deny = $_POST['tenant_id'];
    // Security check: ensure admin can only deny their own tenants
    $deny_stmt = $conn->prepare("DELETE FROM tenants WHERE id = ? AND admin_id = ? AND status = 'pending'");
    $deny_stmt->bind_param("ii", $tenant_id_to_deny, $admin_id);
    if ($deny_stmt->execute() && $deny_stmt->affected_rows > 0) {
        $conn->query("UPDATE system_state SET state_value = UNIX_TIMESTAMP() WHERE state_key = 'last_update_timestamp'");
        $_SESSION['message'] = "<div class='alert alert-info'>Pending tenant application has been denied and removed.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error denying tenant.</div>";
    }
    header("Location: tenants.php" . (!empty($_GET['status']) ? "?status=" . $_GET['status'] : ""));
    exit();
}

// Handle Tenant Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_tenant']) && !in_array($account_status, ['pending', 'restricted'])) {
    $tenant_id_to_update = $_POST['tenant_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $age = $_POST['age'];
    $status = $_POST['status'];
    $start_boarding_date = $_POST['start_boarding_date'];
    $new_room_id = $_POST['room_id'];
    $original_start_date = $_POST['original_start_date']; // Keep for due date recalculation
    $emergency_contact_person = $_POST['emergency_contact_person'];
    $emergency_contact_phone = $_POST['emergency_contact_phone'];

    // Security check: ensure admin can only edit their own tenants
    $update_stmt = $conn->prepare("UPDATE tenants SET fullname = ?, email = ?, phone = ?, age = ?, status = ?, start_boarding_date = ?, emergency_contact_person = ?, emergency_contact_phone = ?, boarding_code = ? WHERE id = ? AND admin_id = ?");
    $update_stmt->bind_param("sssssssssii", $fullname, $email, $phone, $age, $status, $start_boarding_date, $emergency_contact_person, $emergency_contact_phone, $boarding_code, $tenant_id_to_update, $admin_id);

    // Only proceed if there wasn't an error during approval validation
    if (empty($message) && $update_stmt->execute()) {

        // ✅ If status is changed to 'inactive', unassign from room and delete pending payments
        if ($status === 'inactive') {
            // We no longer delete the room assignment. Instead, we'll filter occupancy counts.
            // $delete_assignment = $conn->prepare("DELETE FROM tenant_rooms WHERE tenant_id = ?");
            // $delete_assignment->bind_param("i", $tenant_id_to_update);
            // $delete_assignment->execute();
            
            $delete_pending_payments = $conn->prepare("DELETE FROM payments WHERE tenant_id = ? AND status = 'pending'");
            $delete_pending_payments->bind_param("i", $tenant_id_to_update);
            $delete_pending_payments->execute();
        }
        // If the start boarding date was changed for an existing active tenant, update their next due date.
        if ($status === 'active' && $start_boarding_date !== $original_start_date) {
            // Find the earliest pending payment for this tenant
            $payment_stmt = $conn->prepare("SELECT id, due_date FROM payments WHERE tenant_id = ? AND status = 'pending' ORDER BY due_date ASC LIMIT 1");
            $payment_stmt->bind_param("i", $tenant_id_to_update);
            $payment_stmt->execute();
            $payment_result = $payment_stmt->get_result();

            if ($payment_result->num_rows > 0) {
                $payment = $payment_result->fetch_assoc();
                $payment_id = $payment['id'];
                
                // Calculate the month difference between the original start date and the original due date
                $original_start = new DateTime($original_start_date);
                $original_due = new DateTime($payment['due_date']);
                $interval = $original_start->diff($original_due);
                $months_diff = $interval->y * 12 + $interval->m;

                // Calculate the new due date based on the new start date plus the month difference
                $new_start = new DateTime($start_boarding_date);
                $new_start->add(new DateInterval("P{$months_diff}M"));
                $new_due_date = $new_start->format('Y-m-d');

                $update_payment_stmt = $conn->prepare("UPDATE payments SET due_date = ? WHERE id = ?");
                $update_payment_stmt->bind_param("si", $new_due_date, $payment_id);
                $update_payment_stmt->execute();
            }
        }

        $_SESSION['message'] = "<div class='alert alert-success'>Tenant details updated successfully.</div>";
        header("Location: tenants.php" . (!empty($_GET['status']) ? "?status=" . $_GET['status'] : ""));
        exit(); // Exit immediately after setting the redirect header
        // Handle room assignment change
        // First, remove any existing room assignment for this tenant
        $delete_stmt = $conn->prepare("DELETE FROM tenant_rooms WHERE tenant_id = ?");
        $delete_stmt->bind_param("i", $tenant_id_to_update);
        $delete_stmt->execute();

        if ($new_room_id === 'unassign') {
            // Unassign the tenant and delete their pending payments
            $update_status_stmt = $conn->prepare("UPDATE tenants SET status='unassigned' WHERE id = ? AND admin_id = ?");
            $update_status_stmt->bind_param("ii", $tenant_id_to_update, $admin_id);
            $update_status_stmt->execute();

            // If unassigned, delete pending payments
            $delete_payments = $conn->prepare("DELETE FROM payments WHERE tenant_id = ? AND status = 'pending'");
            $delete_payments->bind_param("i", $tenant_id_to_update);
            $delete_payments->execute();
        } else if (!empty($new_room_id)) {
            // Assign to the new room
            $conn->query("UPDATE system_state SET state_value = UNIX_TIMESTAMP() WHERE state_key = 'last_update_timestamp'");
            $assign_stmt = $conn->prepare("INSERT INTO tenant_rooms (tenant_id, room_id) VALUES (?, ?)");
            $assign_stmt->bind_param("ii", $tenant_id_to_update, $new_room_id);
            $assign_stmt->execute();
        }
    } elseif (empty($message)) {
        $message = "<div class='alert alert-danger'>Error updating tenant. The email might already be in use by another tenant.</div>";
    }
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : "";

// Build query with filter
$sql = "
    SELECT t.id, t.fullname, t.email, t.phone, t.status, t.created_at, r.id as room_id, r.room_label, r.rental_rate, t.age, t.emergency_contact_person, t.emergency_contact_phone,
    t.start_boarding_date, t.requested_room_id, (SELECT MIN(due_date) FROM payments WHERE tenant_id = t.id AND status = 'pending') AS next_due_date
    FROM tenants t
    LEFT JOIN tenant_rooms tr ON t.id = tr.tenant_id
    LEFT JOIN rooms r ON tr.room_id = r.id
    WHERE t.admin_id = ?
";

$params = [$admin_id];
$types = "i";

if (!empty($status_filter)) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY t.fullname ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all rooms with their occupancy for the dropdown
$all_rooms_stmt = $conn->prepare("
    SELECT r.id, r.room_label, r.capacity, r.rental_rate, COUNT(tr.id) AS tenants 
    FROM rooms r LEFT JOIN tenant_rooms tr ON r.id = tr.room_id 
    WHERE r.boarding_code = ? GROUP BY r.id ORDER BY r.room_label ASC");
$all_rooms_stmt->bind_param("s", $boarding_code);
$all_rooms_stmt->execute();
$all_rooms_for_assignment = $all_rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<title>HouseMaster — Tenants Profile</title>
<link rel="stylesheet" href="side.css">
<link rel="stylesheet" href="main.css">
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
    .main {
        padding: 2rem;
    }
    .text-danger {
    color: #32325d !important;
    }
    .page-header {
        margin-bottom: 2rem;
    }
    .page-title {
        color: var(--primary-color);
        font-weight: 800;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }
    .page-subtitle {
        color: #6c757d;
        font-size: 0.95rem;
    }
    
    /* Stat Cards */
    .stat-card {
        border: none;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .stat-card .card-body {
        padding: 1.5rem;
        position: relative;
        z-index: 2;
    }
    .stat-icon-bg {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.05;
        z-index: 1;
        transform: rotate(-15deg);
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 0;
        line-height: 1.2;
    }
    .stat-label {
        color: #8898aa;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }
    .bg-blue-soft { background-color: rgba(5, 68, 94, 0.1); color: #05445E; }
    .bg-green-soft { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .bg-yellow-soft { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .bg-red-soft { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }

    /* Table Card */
    .table-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        background: #fff;
        overflow: hidden;
    }
    .table-card-header {
        background: #fff;
        padding: 1.5rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .table thead th {
        background-color: #f8f9fa;
        color: #8898aa;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }
    .table tbody td {
        padding: 1rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        color: #525f7f;
        font-size: 0.95rem;
    }
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    .table tbody tr:hover {
        background-color: #fcfcfc;
    }
    
    /* Avatar */
    .avatar-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        margin-right: 1rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .tenant-name {
        font-weight: 600;
        color: #32325d;
        display: block;
    }
    .tenant-meta {
        font-size: 0.8rem;
        color: #8898aa;
    }

    /* Badges */
    .status-badge {
        padding: 0.35em 0.8em;
        font-size: 0.75em;
        font-weight: 700;
        border-radius: 30px;
        text-transform: uppercase;
    }
    .badge-active { background-color: #d1e7dd; color: #0f5132; }
    .badge-pending { background-color: #fff3cd; color: #664d03; }
    .badge-inactive { background-color: #f8d7da; color: #842029; }
    .badge-unassigned { background-color: #e2e3e5; color: #41464b; }

    /* Action Buttons */
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
        margin-right: 5px;
    }
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    .btn-view { color: var(--primary-color); background: rgba(5, 68, 94, 0.1); border: none; }
    .btn-view:hover { background: var(--primary-color); color: #fff; }
    
    .btn-edit { color: #ffc107; background: rgba(255, 193, 7, 0.1); border: none; }
    .btn-edit:hover { background: #ffc107; color: #000; }
    
    .btn-history { color: #17a2b8; background: rgba(23, 162, 184, 0.1); border: none; }
    .btn-history:hover { background: #17a2b8; color: #fff; }

    .btn-approve { color: #198754; background: rgba(25, 135, 84, 0.1); border: none; }
    .btn-approve:hover { background: #198754; color: #fff; }

    .btn-deny { color: #dc3545; background: rgba(220, 53, 69, 0.1); border: none; }
    .btn-deny:hover { background: #dc3545; color: #fff; }

    /* Modal Enhancements */
    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    .modal-header {
        background-color: #fff;
        border-bottom: 1px solid #f0f0f0;
        padding: 1.5rem 2rem;
        border-radius: 16px 16px 0 0;
    }
    .modal-title {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.25rem;
    }
    .modal-body {
        padding: 2rem;
    }
    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #f0f0f0;
        padding: 1.25rem 2rem;
        border-radius: 0 0 16px 16px;
    }
    
    /* Detail View Styles */
    .detail-group {
        margin-bottom: 0.5rem;
    }
    .detail-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8898aa;
        font-weight: 600;
        margin-bottom: 0.35rem;
        display: block;
    }
    .detail-value {
        font-size: 1rem;
        color: #32325d;
        font-weight: 500;
        word-break: break-word;
    }
    .detail-icon {
        width: 32px;
        height: 32px;
        background: rgba(5, 68, 94, 0.05);
        color: var(--primary-color);
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        font-size: 0.9rem;
    }

    /* Form Styles */
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
    .form-section-title:first-child {
        margin-top: 0;
    }
    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #525f7f;
        margin-bottom: 0.4rem;
    }
    .form-control, .form-select {
        border: 1px solid #e0e6ed;
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
        color: #32325d;
        transition: all 0.2s;
    }
    .form-select:hover {
        background-color: #e9ecef !important; /* Manually edit hover color here */
        cursor: pointer;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 3px rgba(24, 154, 180, 0.1);
    }
    .form-text {
        font-size: 0.8rem;
        color: #8898aa;
    }

    /* Split Modal Styles */
    .modal-split-layout {
        display: flex;
        flex-direction: row;
        min-height: 420px;
    }
    @media (max-width: 768px) {
        .modal-split-layout {
            flex-direction: column;
        }
        .modal-split-sidebar {
            width: 100% !important;
            border-right: none !important;
            border-bottom: 1px solid #edf2f9;
            padding: 1.5rem !important;
        }
        .modal-split-content {
            width: 100% !important;
            padding: 1.5rem !important;
        }
    }
    .modal-split-sidebar {
        width: 35%;
        background-color: #f8f9fa;
        border-right: 1px solid #edf2f9;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .modal-split-content {
        width: 65%;
        padding: 2.5rem;
        background-color: #fff;
        position: relative;
    }
    .btn-primary
    {
        --bs-btn-color: #fff;
        --bs-btn-bg: #05445E;
        --bs-btn-border-color: #05445E;
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: #05445E;
        --bs-btn-hover-border-color: #05445E;
        --bs-btn-focus-shadow-rgb: 49, 132, 253;
        --bs-btn-active-color: #fff;
        --bs-btn-active-bg: #05445E;
        --bs-btn-active-border-color: #05445E;
        --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
        --bs-btn-disabled-color: #fff;
        --bs-btn-disabled-bg: #05445E;
        --bs-btn-disabled-border-color: #05445E;
    }
    .split-avatar {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: #05445E;
        color: #fff;
        font-size: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        box-shadow: 0 8px 20px rgba(5, 68, 94, 0.15);
    }
    .split-name {
        font-size: 1.2rem;
        font-weight: 800;
        color: #32325d;
        margin-bottom: 0.2rem;
    }
    .split-meta {
        color: #8898aa;
        font-size: 0.95rem;
        margin-bottom: 1rem;
    }
    .info-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #adb5bd;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }
    .info-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #f0f0f0;
        margin-left: 10px;
    }
    .info-item {
        margin-bottom: 1rem;
    }
    .info-label {
        font-size: 0.8rem;
        color: #8898aa;
        margin-bottom: 0.2rem;
    }
    .info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #32325d;
    }
</style>
  <!-- Sidebar -->
  <?php include("navbar.php"); ?>

  <!-- Main Content -->
  <div class="main">
    <div class="page-header">
        <h3 class="page-title">Tenants Profile</h3>
        <p class="page-subtitle">Manage your tenants, view their details, and track their status.</p>
    </div>

    <?php if (!empty($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <!-- Status Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body">
                    <div class="icon-circle bg-blue-soft">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_tenants; ?></h3>
                    <span class="stat-label">Total Tenants</span>
                    <i class="fas stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body">
                    <div class="icon-circle bg-green-soft">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $status_counts['active']; ?></h3>
                    <span class="stat-label">Active Tenants</span>
                    <i class="fas stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body">
                    <div class="icon-circle bg-yellow-soft">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $status_counts['pending']; ?></h3>
                    <span class="stat-label">Pending Approval</span>
                    <i class="fas  stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="card-body">
                    <div class="icon-circle bg-red-soft">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $status_counts['inactive']; ?></h3>
                    <span class="stat-label">Inactive Tenants</span>
                    <i class="fas  stat-icon-bg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
      <div class="table-card-header">
        <h5 class="mb-0 fw-bold text-dark">Tenant List</h5>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label class="small text-muted me-1">Filter:</label>
            <select name="status" class="form-select form-select-sm border-0 bg-light" style="width: 150px; font-weight: 600;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" <?php if ($status_filter == "active") echo "selected"; ?>>Active</option>
                <option value="pending" <?php if ($status_filter == "pending") echo "selected"; ?>>Pending</option>
                <option value="inactive" <?php if ($status_filter == "inactive") echo "selected"; ?>>Inactive</option>
            </select>
            <?php if(!empty($status_filter)): ?>
                <a href="tenants.php" class="btn btn-sm btn-light text-muted" title="Reset Filter"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Room</th>
              <th>Contact Info</th>
              <th>Status</th>
              <th>Rent Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                  // Moved this calculation to the top of the loop so it's available for both the table row and the modal.
                  $days_remaining = null;
                  if ($row['next_due_date']) {
                      $today = new DateTime();
                      // Set time to 00:00:00 to compare dates only, avoiding time-of-day issues.
                      $today->setTime(0, 0, 0);
                      $dueDate = new DateTime($row['next_due_date']);
                      $dueDate->setTime(0, 0, 0);
                      $interval = $today->diff($dueDate);
                      $days_remaining = (int)$interval->format('%r%a');
                  }
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-wrapper">
                            <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                        </div>
                        <div>
                            <span class="tenant-name"><?php echo htmlspecialchars($row['fullname']); ?></span>
                            <span class="tenant-meta">Age: <?php echo htmlspecialchars($row['age']); ?> • Joined <?php echo date("M Y", strtotime($row['created_at'])); ?></span>
                        </div>
                    </div>
                  </td>
                  <td>
                      <?php if ($row['room_label']): ?>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['room_label']); ?></span>
                        <br><small class="text-muted">₱<?php echo number_format($row['rental_rate']); ?>/mo</small>
                      <?php else: ?>
                        <span class="text-muted fst-italic">Unassigned</span>
                      <?php endif; ?>
                  </td>
                  <td>
                      <div class="d-flex flex-column">
                          <span class="small"><i class="fas fa-phone-alt me-2 text-muted" style="width:15px;"></i><?php echo htmlspecialchars($row['phone']); ?></span>
                          <span class="small"><i class="fas fa-envelope me-2 text-muted" style="width:15px;"></i><?php echo htmlspecialchars($row['email']); ?></span>
                      </div>
                  </td>
                  <td>
                    <?php
                      $status = $row['status'];
                      $badgeClass = match($status) {
                          "active" => "badge-active",
                          "pending" => "badge-pending",
                          "inactive" => "badge-inactive",
                          "unassigned" => "badge-unassigned",
                          default => "badge-unassigned"
                      };
                    ?>
                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                  </td>
                  <td>
                    <?php
                      if ($days_remaining !== null && $account_status !== 'pending') {
                          if ($days_remaining < 0) {
                              echo '<span class="text-danger fw-bold small"><i class="fas fa-exclamation-circle me-1"></i>Overdue (' . abs($days_remaining) . 'd)</span>';
                          } elseif ($days_remaining <= 7) {
                              echo '<span class="text-warning fw-bold small"><i class="fas fa-clock me-1"></i>Due in ' . $days_remaining . 'd</span>';
                          } else {
                              echo '<span class="text-success small"><i class="fas fa-check-circle me-1"></i>' . $days_remaining . ' days left</span>';
                          }
                      } else {
                          echo '<span class="text-muted small">-</span>';
                      }
                    ?>
                  </td>
                  <td class="text-end">
                    <?php if ($row['status'] === 'pending' && !in_array($account_status, ['pending', 'restricted'])): ?>
                        <button type="button" class="btn-icon btn-approve" title="Approve Tenant" 
                                data-bs-toggle="modal" 
                                data-bs-target="#approveModal"
                                data-tenant-id="<?php echo $row['id']; ?>"
                                data-tenant-name="<?php echo htmlspecialchars($row['fullname']); ?>"
                                data-requested-room-id="<?php echo $row['requested_room_id']; ?>"
                                <?php if (empty($row['requested_room_id'])) echo 'disabled title="Tenant has not requested a room."'; ?>>
                            <i class="fas fa-check"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to deny and delete this tenant?');" title="Deny Tenant">
                            <input type="hidden" name="tenant_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="deny_tenant" class="btn-icon btn-deny" title="Deny Tenant"><i class="fas fa-times"></i></button>
                        </form>
                    <?php else: ?>
                    <?php endif; ?>
                    
                    <button class="btn-icon btn-view" data-bs-toggle="modal" data-bs-target="#tenant<?php echo $row['id']; ?>" title="View Details"><i class="fas fa-eye"></i></button>
                    
                    <?php if ($row['status'] !== 'pending'): ?>
                      <a href="payments.php?tenant_id=<?php echo $row['id']; ?>" class="btn-icon btn-history" title="View Payment History"><i class="fas fa-history"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-5 text-muted">No tenants found matching your criteria.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <footer class="mt-5 text-center text-muted small">HouseMaster © 2025 — Boarding House & Dormitory Management System</footer>
  </div>

  <!-- Modals Section -->
  <?php
    // Reset the result pointer and loop again to generate modals outside the table
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()):
      // Recalculate days_remaining for this scope
      $days_remaining = null;
      if ($row['next_due_date']) {
          $today = new DateTime();
          $today->setTime(0, 0, 0);
          $dueDate = new DateTime($row['next_due_date']);
          $dueDate->setTime(0, 0, 0);
          $interval = $today->diff($dueDate);
          $days_remaining = (int)$interval->format('%r%a');
      }
      
      $status = $row['status'];
      $badgeClass = match($status) {
          "active" => "badge-active",
          "pending" => "badge-pending",
          "inactive" => "badge-inactive",
          "unassigned" => "badge-unassigned",
          default => "badge-unassigned"
      };
  ?>
      <!-- Tenant Modal -->
      <div class="modal fade" id="tenant<?php echo $row['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content border-0 overflow-hidden" style="border-radius: 16px;">
            <div class="modal-split-layout">
                <!-- Sidebar -->
                <div class="modal-split-sidebar">
                    <div class="split-avatar">
                        <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                    </div>
                    <h5 class="split-name"><?php echo htmlspecialchars($row['fullname']); ?></h5>
                   
                    <span class="status-badge <?php echo $badgeClass; ?> mb-4"><?php echo ucfirst($row['status']); ?></span>
                    
                    <div class="w-100 mt-auto">
                        <?php if ($row['status'] === 'pending' && !in_array($account_status, ['pending', 'restricted'])): ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#approveModal" data-tenant-id="<?php echo $row['id']; ?>" data-tenant-name="<?php echo htmlspecialchars($row['fullname']); ?>" data-requested-room-id="<?php echo $row['requested_room_id']; ?>" <?php if (empty($row['requested_room_id'])) echo 'disabled'; ?>>
                                    <i class="fas fa-check me-2"></i>Approve
                                </button>
                                <form method="POST" class="d-grid" onsubmit="return confirm('Deny this tenant?');">
                                    <input type="hidden" name="tenant_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="deny_tenant" class="btn btn-outline-danger btn-sm"><i class="fas fa-times me-2"></i>Deny</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#paymentHistoryModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-history me-2"></i>Payments
                                </button>
                                <?php if ($row['status'] === 'active' && !in_array($account_status, ['pending', 'restricted'])): ?>
                                    <form method="POST" onsubmit="return confirm('Generate the next month\'s bill for this tenant? This allows for advance payment.');">
                                        <input type="hidden" name="tenant_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="rental_rate" value="<?php echo $row['rental_rate']; ?>">
                                        <button type="submit" name="generate_bill" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-file-invoice-dollar me-2"></i>Generate Next Bill
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($row['status'] !== 'inactive' && !in_array($account_status, ['pending', 'restricted'])): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editTenant<?php echo $row['id']; ?>">
                                        <i class="fas fa-edit me-2"></i>Edit Profile
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-link text-muted btn-sm mt-3 text-decoration-none" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>

                <!-- Content -->
                <div class="modal-split-content">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    
                    <div class="info-section-title">Contact Details</div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value text-break"><?php echo htmlspecialchars($row['email']); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($row['phone']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section-title">Room & Stay</div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Assigned Room</div>
                                <div class="info-value">
                                    <?php if ($row['room_label']): ?>
                                        <?php echo htmlspecialchars($row['room_label']); ?>
                                        <div class="small text-muted fw-normal">₱<?php echo number_format($row['rental_rate']); ?>/mo</div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Unassigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Start Date</div>
                                <div class="info-value">
                                    <?php echo $row['start_boarding_date'] ? date("M d, Y", strtotime($row['start_boarding_date'])) : '<span class="text-muted">-</span>'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Age</div>
                                <div class="info-value"><?php echo htmlspecialchars($row['age']); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-item">
                                <div class="info-label">Joined</div>
                                <div class="info-value"><?php echo date("M Y", strtotime($row['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section-title text-danger" style="color: #dc3545;">Emergency Contact</div>
                    <div class="p-3 rounded bg-light border border-light">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-danger"><i class="fas fa-user-shield fa-lg"></i></div>
                            <div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['emergency_contact_person']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($row['emergency_contact_phone']); ?></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Tenant Modal -->
      <div class="modal fade" id="editTenant<?php echo $row['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <form method="POST">
              <div class="modal-header">
                <h5 class="modal-title">Edit Tenant Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <input type="hidden" name="tenant_id" value="<?php echo $row['id']; ?>">
                  <input type="hidden" name="original_start_date" value="<?php echo htmlspecialchars($row['start_boarding_date']); ?>">
                  
                  <div class="row g-4">
                      <!-- Personal Info -->
                      <div class="col-12">
                          <h6 class="form-section-title">Personal Information</h6>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Full Name</label>
                          <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($row['fullname']); ?>" required>
                      </div>
                      <div class="col-md-3">
                          <label class="form-label">Age</label>
                          <input type="number" name="age" class="form-control" value="<?php echo htmlspecialchars($row['age']); ?>" required>
                      </div>
                      <div class="col-md-3">
                          <label class="form-label">Status</label>
                          <select name="status" class="form-select" required <?php if ($row['status'] == 'inactive') echo 'disabled'; ?>>
                              <option value="active" <?php if ($row['status'] == 'active') echo 'selected'; ?>>Active</option>
                              <option value="inactive" <?php if ($row['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                          </select>
                      </div>

                      <!-- Contact Info -->
                      <div class="col-12">
                          <h6 class="form-section-title">Contact Details</h6>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Email Address</label>
                          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($row['email']); ?>" required>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Phone Number</label>
                          <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($row['phone']); ?>" required>
                      </div>

                      <!-- Emergency Contact -->
                      <div class="col-12">
                          <h6 class="form-section-title">Emergency Contact</h6>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Contact Person</label>
                          <input type="text" name="emergency_contact_person" class="form-control" value="<?php echo htmlspecialchars($row['emergency_contact_person']); ?>" required>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Contact Phone</label>
                          <input type="text" name="emergency_contact_phone" class="form-control" value="<?php echo htmlspecialchars($row['emergency_contact_phone']); ?>" required>
                      </div>

                      <!-- Tenancy Info -->
                      <div class="col-12">
                          <h6 class="form-section-title">Tenancy Information</h6>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Start Boarding Date</label>
                          <input type="date" name="start_boarding_date" class="form-control" value="<?php echo htmlspecialchars($row['start_boarding_date'] ?? ''); ?>" <?php if($row['status'] !== 'pending') echo 'required'; ?>>
                      </div>
                      <div class="col-md-6">
                          <label class="form-label">Room Assignment</label>
                          <select name="room_id" class="form-select">
                              <option value="unassign">Unassign</option>
                              <?php foreach ($all_rooms_for_assignment as $room_option): ?>
                                  <?php 
                                      $is_available = $room_option['tenants'] < $room_option['capacity']; 
                                      $is_current_room = $room_option['id'] == $row['room_id']; 
                                      if ($is_available || $is_current_room): 
                                  ?>
                                      <option value="<?php echo $room_option['id']; ?>" <?php if ($is_current_room) echo 'selected'; ?>>
                                          <?php echo htmlspecialchars($room_option['room_label']); ?> 
                                          (<?php echo $room_option['tenants']; ?>/<?php echo $room_option['capacity']; ?>)
                                      </option>
                                  <?php endif; ?>
                              <?php endforeach; ?>
                          </select>
                          <div class="form-text mt-1">Changing this will move the tenant to a new room.</div>
                      </div>
                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_tenant" class="btn btn-primary px-4">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Payment History Modal -->
      <div class="modal fade" id="paymentHistoryModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="paymentHistoryModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-blue-soft me-3" style="width: 40px; height: 40px; font-size: 1.1rem; margin-bottom: 0;">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Payment History</h5>
                        <small class="text-muted"><?php echo htmlspecialchars($row['fullname']); ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
              <!-- Filters for the modal -->
              <div class="p-3 bg-light border-bottom">
                  <div class="row g-2">
                      <div class="col-md-6">
                          <div class="input-group input-group-sm">
                              <span class="input-group-text border-0 bg-white"><i class="fas fa-filter text-muted"></i></span>
                              <select class="form-select border-0 payment-history-filter" data-tenant-id="<?php echo $row['id']; ?>" data-filter-type="status">
                                  <option value="">All Statuses</option>
                                  <option value="pending">Pending</option>
                                  <option value="overdue">Overdue</option>
                                  <option value="paid">Paid</option>
                              </select>
                          </div>
                      </div>
                      <div class="col-md-6">
                          <div class="input-group input-group-sm">
                              <span class="input-group-text border-0 bg-white"><i class="far fa-calendar-alt text-muted"></i></span>
                              <select class="form-select border-0 payment-history-filter" data-tenant-id="<?php echo $row['id']; ?>" data-filter-type="month">
                                  <option value="">All Months</option>
                                  <?php
                                  $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
                                  foreach ($months as $num => $name) {
                                      echo "<option value='{$num}'>{$name}</option>";
                                  }
                                  ?>
                              </select>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-4">Amount</th>
                            <th>Due Date</th>
                            <th class="text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistoryContent<?php echo $row['id']; ?>">
                      <!-- Content will be loaded via AJAX -->
                      <tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>
                    </tbody>
                  </table>
              </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
  <?php endwhile; ?>

  <!-- Approve & Bill Modal -->
  <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <div class="modal-header">
            <div class="d-flex align-items-center">
                <div class="icon-circle bg-green-soft me-3" style="width: 40px; height: 40px; font-size: 1.1rem; margin-bottom: 0;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h5 class="modal-title mb-0">Approve Tenant</h5>
                    <small class="text-muted">Generate first bill & activate</small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-light border mb-3"><i class="fas fa-info-circle text-primary me-2"></i>You are approving <strong id="approveTenantName" class="text-dark"></strong>.</div>
            <input type="hidden" name="tenant_id" id="approveTenantId">
            <input type="hidden" name="rental_rate" id="approveRentalRate">
            <input type="hidden" name="requested_room_id" id="approveRequestedRoomId">
            
            <div class="mb-3">
              <label for="startDate" class="form-label">Start Boarding Date</label>
              <input type="date" class="form-control" id="startDate" name="start_date" required>
            </div>
            <div class="d-flex align-items-start text-muted small">
                <i class="fas fa-file-invoice-dollar mt-1 me-2"></i>
                <div>The first bill will be automatically generated and due one month after this date.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="approve_and_bill" class="btn btn-success px-4">Confirm Approval</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Custom polling for auto-refresh -->
  <script>
    // Pass the server's last update timestamp to the JavaScript poller.
    <?php
        $ts_stmt = $conn->query("SELECT state_value FROM system_state WHERE state_key = 'last_update_timestamp'");
        $current_ts = $ts_stmt->fetch_assoc()['state_value'];
    ?>
    window.housemaster_last_update = <?php echo $current_ts ?: 0; ?>;
  </script>
  <script src="../assets/js/autoupdate.js"></script>
  <script>
    <?php
      $room_rates_map = [];
      foreach ($all_rooms_for_assignment as $room) {
          $room_rates_map[$room['id']] = $room['rental_rate'];
      }
    ?>
    const roomRentalRates = <?php echo json_encode($room_rates_map); ?>;

    // Pass data to the Approve & Bill modal
    const approveModal = document.getElementById('approveModal');
    approveModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const requestedRoomId = button.getAttribute('data-requested-room-id');

        document.getElementById('approveTenantId').value = button.getAttribute('data-tenant-id');
        document.getElementById('approveRequestedRoomId').value = requestedRoomId;
        document.getElementById('approveTenantName').textContent = button.getAttribute('data-tenant-name');
        
        // Set the rental rate based on the requested room
        document.getElementById('approveRentalRate').value = roomRentalRates[requestedRoomId] || '';

        // Set the start date to today in a cross-browser compatible way (YYYY-MM-DD)
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('startDate').value = `${yyyy}-${mm}-${dd}`;
    });

    // --- Payment History Modal AJAX ---
    const paymentHistoryModals = document.querySelectorAll('.modal[id^="paymentHistoryModal"]');

    // Function to load payment history
    function loadPaymentHistory(tenantId, status = '', month = '') {
        const contentArea = document.getElementById(`paymentHistoryContent${tenantId}`);
        if (!contentArea) return;

        contentArea.innerHTML = '<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';
        
        const url = `get_payment_history.php?tenant_id=${tenantId}&status=${status}&month=${month}`;

        fetch(url)
            .then(response => response.text())
            .then(data => {
                contentArea.innerHTML = data;
            })
            .catch(error => {
                contentArea.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>';
                console.error('Error:', error);
            });
    }

    // Add event listeners to all payment history modals
    paymentHistoryModals.forEach(modal => {
        const tenantId = modal.id.replace('paymentHistoryModal', '');
        modal.addEventListener('show.bs.modal', function () {
            // Load initial data when modal is shown
            loadPaymentHistory(tenantId);
        });

        // Add listeners to filter dropdowns inside this modal
        modal.querySelectorAll('.payment-history-filter').forEach(filterInput => {
            filterInput.addEventListener('change', function() {
                const status = modal.querySelector('[data-filter-type="status"]').value;
                const month = modal.querySelector('[data-filter-type="month"]').value;
                loadPaymentHistory(tenantId, status, month);
            });
        });
    });
  </script>
</body>
</html>
