<?php
session_start();
include __DIR__ . "/../config.php";


if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    die("Access Denied");
}

$admin_id = $_SESSION['admin_id'];
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;


$verify_stmt = $conn->prepare("SELECT id FROM tenants WHERE id = ? AND admin_id = ?");
$verify_stmt->bind_param("ii", $tenant_id, $admin_id);
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    die("Permission Denied");
}

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : "";
$month_filter = isset($_GET['month']) ? $_GET['month'] : "";

$is_overdue_filter = ($status_filter === 'overdue');
if ($is_overdue_filter) {
    $status_filter = 'pending'; 
}


$sql = "SELECT amount, due_date, status FROM payments WHERE tenant_id = ?";
$params = [$tenant_id];
$types = "i";

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($is_overdue_filter) {
    $sql .= " AND due_date < CURDATE()";
}

if (!empty($month_filter)) {
    $sql .= " AND MONTH(due_date) = ?";
    $params[] = $month_filter;
    $types .= "i";
}

$sql .= " ORDER BY due_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($payment = $result->fetch_assoc()) {
        $payment_status = $payment['status'];
        $display_status = ucfirst($payment_status);
        
        
        $badge_class = 'badge-pending';
        $icon = 'clock';

        if ($payment_status == 'paid') {
            $badge_class = 'badge-active';
            $icon = 'check-circle';
        } elseif ($payment_status == 'pending' && strtotime($payment['due_date']) < time()) {
            $display_status = 'Overdue';
            $badge_class = 'badge-inactive';
            $icon = 'exclamation-circle';
        }

        echo "<tr>";
        echo "<td class='ps-4 fw-bold text-dark'>₱" . number_format($payment['amount'], 2) . "</td>";
        echo "<td class='text-muted'><i class='far fa-calendar me-2'></i>" . date("M d, Y", strtotime($payment['due_date'])) . "</td>";
        echo "<td class='text-end pe-4'><span class='status-badge {$badge_class}'><i class='fas fa-{$icon} me-1'></i>{$display_status}</span></td>";
        echo "</tr>";
    }
} else {
    echo '<tr><td colspan="3" class="text-center py-4 text-muted">No payment history found for the selected filters.</td></tr>';
}
?>