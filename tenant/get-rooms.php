<?php
header('Content-Type: application/json');
include __DIR__ . "/../config.php";

$boarding_code = $_GET['boarding_code'] ?? '';

if (empty($boarding_code)) {
    echo json_encode(['error' => 'Boarding code is required.']);
    exit;
}

// Check if boarding code is valid first
$admin_check_stmt = $conn->prepare("SELECT id FROM admins WHERE boarding_code = ?");
$admin_check_stmt->bind_param("s", $boarding_code);
$admin_check_stmt->execute();
$admin_result = $admin_check_stmt->get_result();

if ($admin_result->num_rows === 0) {
    echo json_encode(['error' => 'Invalid boarding house code.']);
    exit;
}

// Fetch available rooms for the valid boarding code
$stmt = $conn->prepare("
    SELECT r.id, r.room_label, r.capacity, COUNT(tr.id) AS tenants 
    FROM rooms r 
    LEFT JOIN tenant_rooms tr ON r.id = tr.room_id 
    WHERE r.boarding_code = ? 
    GROUP BY r.id 
    HAVING tenants < r.capacity");
$stmt->bind_param("s", $boarding_code);
$stmt->execute();
$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($rooms);