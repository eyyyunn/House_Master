<?php
// =============================
// Database connection settings
// =============================
$host = "localhost";   // MySQL server
$user = "root";        // MySQL username
$pass = "";            // MySQL password
$dbname = "housemaster_db";

// 1. Connect to MySQL server
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if (!$conn->query($sql)) {
    die("Database creation failed: " . $conn->error);
}

// 3. Select the database
$conn->select_db($dbname);

// =============================
// Admin credentials (hardcoded)
// =============================
define("ADMIN_USER", "admin");        // default admin username
define("ADMIN_PASS", "admin123");     // default admin password

// =============================
// Super Admin credentials (hardcoded)
// =============================
define("SUPER_ADMIN_USER", "superadmin");    // default super admin username
define("SUPER_ADMIN_PASS", "superadmin123"); // default super admin password

// =============================
// SMTP Email Settings (for PHPMailer)
// =============================
define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_USER", "yboian577@gmail.com"); // Your Gmail address
define("SMTP_PASS", "keji yfhp hsmr uomt");      // Your Gmail App Password
define("SMTP_SECURE", "ssl"); // Use 'ssl' (for port 465) or 'tls' (for port 587)
define("SMTP_PORT", 465);     // 465 for 'ssl', 587 for 'tls'
define("SMTP_FROM_NAME", "HouseMaster");

// =============================
// TextBee SMS Gateway Settings
// =============================
// 1. The IP address and port shown in your TextBee Android app.
//    When using a mobile hotspot, the IP is usually 192.168.43.1
define('TEXTBEE_GATEWAY_URL', 'http://192.168.43.1:8080/v1/gateway/send');

// 2. The API Key from your TextBee app's settings.
define('TEXTBEE_API_KEY', '2649afd-3535-4563-a5be-a76bc1fc444c');

// =============================
// Create tables
// =============================

// Tenants table
// Added boarding_code and admin_id from the start.
$conn->query("
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT,
    phone VARCHAR(20),
    reset_token VARCHAR(64) NULL DEFAULT NULL,
    reset_token_expires_at DATETIME NULL DEFAULT NULL,
    status ENUM('active','pending','inactive','unassigned') DEFAULT 'unassigned',
    boarding_code VARCHAR(20),
    start_boarding_date DATE NULL DEFAULT NULL,
    emergency_contact_person VARCHAR(100) NULL DEFAULT NULL,
    emergency_contact_phone VARCHAR(20) NULL DEFAULT NULL,
    admin_id INT NULL,
    requested_room_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

// ✅ Create 'admins' table if it doesn't exist
$conn->query("
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    boarding_code VARCHAR(20) NOT NULL UNIQUE,
    account_status ENUM('active', 'payment_due', 'suspended', 'pending') NOT NULL DEFAULT 'pending',
    reset_token VARCHAR(64) NULL,
    reset_token_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ Self-healing script for the admins table (add reset token fields)
$check_admin_reset_token = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'reset_token'");
if ($check_admin_reset_token->num_rows == 0) {
    $conn->query("ALTER TABLE `admins` ADD `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `boarding_code`, ADD `reset_token_expires_at` DATETIME NULL DEFAULT NULL AFTER `reset_token`");
}

// ✅ Self-healing script for the admins table (add account_status)
$check_admin_status = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'account_status'");
if ($check_admin_status->num_rows == 0) {
    $conn->query("ALTER TABLE `admins` ADD `account_status` ENUM('active', 'payment_due', 'suspended', 'pending') NOT NULL DEFAULT 'pending' AFTER `boarding_code`");
}

// ✅ Update account_status ENUM to include 'restricted' and migrate 'suspended'
$conn->query("ALTER TABLE `admins` MODIFY `account_status` ENUM('active', 'payment_due', 'restricted', 'pending', 'suspended') NOT NULL DEFAULT 'pending'");
$conn->query("UPDATE `admins` SET `account_status` = 'restricted' WHERE `account_status` = 'suspended'");
$conn->query("ALTER TABLE `admins` MODIFY `account_status` ENUM('active', 'payment_due', 'restricted', 'pending') NOT NULL DEFAULT 'pending'");

// ✅ Self-healing script for the admins table (add payment_proof)
$check_payment_proof = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'payment_proof'");
if ($check_payment_proof->num_rows == 0) {
    $conn->query("ALTER TABLE `admins` ADD `payment_proof` VARCHAR(255) NULL DEFAULT NULL AFTER `boarding_code`");
}

// ✅ Self-healing script for the admins table (add payment_method)
$check_payment_method = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'payment_method'");
if ($check_payment_method->num_rows == 0) {
    $conn->query("ALTER TABLE `admins` ADD `payment_method` VARCHAR(50) NULL DEFAULT NULL AFTER `payment_proof`");
}

// ✅ Self-healing script for the admins table (add selected_plan_id)
$check_selected_plan = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'selected_plan_id'");
if ($check_selected_plan->num_rows == 0) {
    // Default to 1 (Standard Monthly) for existing records to prevent errors
    $conn->query("ALTER TABLE `admins` ADD `selected_plan_id` INT NOT NULL DEFAULT 1 AFTER `boarding_code`");
}


// Rooms table
// Made room_label unique per boarding_code, not globally.
$conn->query("
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_label VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    rental_rate DECIMAL(10,2) NOT NULL,
    notice TEXT,
    room_code VARCHAR(20) UNIQUE,
    boarding_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `room_label_boarding_code` (`room_label`, `boarding_code`)
) ENGINE=InnoDB
");

// ✅ Self-healing script for the rooms table indexes
// Check if the old 'room_label' unique index exists and drop it
$check_old_room_label_index = $conn->query("SHOW INDEX FROM `rooms` WHERE Key_name = 'room_label' AND Non_unique = 0");
if ($check_old_room_label_index && $check_old_room_label_index->num_rows > 0) {
    $conn->query("ALTER TABLE `rooms` DROP INDEX `room_label`");
}

// Ensure the composite unique index 'room_label_boarding_code' exists
$check_composite_index = $conn->query("SHOW INDEX FROM `rooms` WHERE Key_name = 'room_label_boarding_code' AND Non_unique = 0");
if ($check_composite_index && $check_composite_index->num_rows == 0) {
    // This query will add the composite unique key if it doesn't exist.
    // If there are existing duplicate (room_label, boarding_code) pairs, this query will fail.
    $conn->query("ALTER TABLE `rooms` ADD UNIQUE KEY `room_label_boarding_code` (`room_label`, `boarding_code`)");
}

// ✅ Create room_images table
$conn->query("
CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB
");

// ✅ Self-healing script: Migrate single room_image to new room_images table and drop old column
$check_room_image_col = $conn->query("SHOW COLUMNS FROM `rooms` LIKE 'room_image'");
if ($check_room_image_col->num_rows > 0) {
    $rooms_with_images = $conn->query("SELECT id, room_image FROM rooms WHERE room_image IS NOT NULL AND room_image != ''");
    if ($rooms_with_images && $rooms_with_images->num_rows > 0) {
        $insert_image_stmt = $conn->prepare("INSERT INTO room_images (room_id, image_filename) VALUES (?, ?)");
        while ($room = $rooms_with_images->fetch_assoc()) {
            $insert_image_stmt->bind_param("is", $room['id'], $room['room_image']);
            $insert_image_stmt->execute();
        }
    }
    $conn->query("ALTER TABLE `rooms` DROP COLUMN `room_image`");
}

// Tenant-Room Assignment (many tenants → one room)
$conn->query("
CREATE TABLE IF NOT EXISTS tenant_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    room_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB
");

// Payments table
// Added admin_id and boarding_code for better filtering.
$conn->query("
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    admin_id INT NOT NULL,
    boarding_code VARCHAR(20),
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending','paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB
");

// ✅ Self-healing script for the payments table
// 1. Check for and add 'admin_id' if it's missing.
$check_admin_id_payments = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'admin_id'");
if ($check_admin_id_payments->num_rows == 0) {
    $conn->query("ALTER TABLE `payments` ADD `admin_id` INT NOT NULL AFTER `tenant_id`");
}
// 2. Check for and add 'boarding_code' if it's missing.
$check_boarding_code_payments = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'boarding_code'");
if ($check_boarding_code_payments->num_rows == 0) {
    $conn->query("ALTER TABLE `payments` ADD `boarding_code` VARCHAR(20) AFTER `admin_id`");
}

// 3. Check for and add 'payment_proof' if it's missing.
$check_payment_proof_payments = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'payment_proof'");
if ($check_payment_proof_payments->num_rows == 0) {
    $conn->query("ALTER TABLE `payments` ADD `payment_proof` VARCHAR(255) NULL DEFAULT NULL AFTER `status`");
}

// ✅ Self-healing script for the tenants table (add reset token fields)
$check_tenant_reset_token = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'reset_token'");
if ($check_tenant_reset_token->num_rows == 0) {
    $conn->query("ALTER TABLE `tenants` ADD `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `phone`, ADD `reset_token_expires_at` DATETIME NULL DEFAULT NULL AFTER `reset_token`");
}

// ✅ Self-healing script for the tenants table (add start_boarding_date)
$check_tenant_start_date = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'start_boarding_date'");
if ($check_tenant_start_date->num_rows == 0) {
    $conn->query("ALTER TABLE `tenants` ADD `start_boarding_date` DATE NULL DEFAULT NULL AFTER `phone`");
}

// ✅ Self-healing script for tenants table (add emergency contact fields)
$check_ec_person = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'emergency_contact_person'");
if ($check_ec_person->num_rows == 0) {
    $conn->query("ALTER TABLE `tenants` ADD `emergency_contact_person` VARCHAR(100) NULL DEFAULT NULL AFTER `start_boarding_date`");
}

$check_ec_phone = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'emergency_contact_phone'");
if ($check_ec_phone->num_rows == 0) {
    $conn->query("ALTER TABLE `tenants` ADD `emergency_contact_phone` VARCHAR(20) NULL DEFAULT NULL AFTER `emergency_contact_person`");
}

// ✅ Self-healing script for tenants table (add requested_room_id)
$check_requested_room_id = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'requested_room_id'");
if ($check_requested_room_id->num_rows == 0) {
    $conn->query("ALTER TABLE `tenants` ADD `requested_room_id` INT NULL DEFAULT NULL AFTER `admin_id`");
}

// Messages (tenant ↔ admin)
$conn->query("
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('tenant','admin','system') NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

// ✅ Self-healing script for the messages table (add is_read column)
$check_is_read = $conn->query("SHOW COLUMNS FROM `messages` LIKE 'is_read'");
if ($check_is_read->num_rows == 0) {
    $conn->query("ALTER TABLE `messages` ADD `is_read` BOOLEAN NOT NULL DEFAULT FALSE AFTER `message`");
}

// ✅ Self-healing script to update sender_type ENUM to include 'system'
$conn->query("ALTER TABLE `messages` MODIFY `sender_type` ENUM('tenant','admin','system') NOT NULL");

// Notices (admin posts to board)
$conn->query("
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    boarding_code VARCHAR(20),
    admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `boarding_code` (`boarding_code`),
    KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB
");

// Room Rules table
$conn->query("
CREATE TABLE IF NOT EXISTS room_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    admin_id INT NOT NULL,
    type ENUM('rule') NOT NULL DEFAULT 'rule',
    rule_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB
");

// ✅ Room Items table (new)
$conn->query("
CREATE TABLE IF NOT EXISTS room_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    admin_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    `condition` ENUM('New', 'Good', 'Used', 'Damaged') NOT NULL DEFAULT 'Good',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB
");

// ✅ Self-healing script to update the item condition options
$conn->query("UPDATE room_items SET `condition` = 'Used' WHERE `condition` IN ('Good', 'Damaged')");
$conn->query("ALTER TABLE `room_items` MODIFY `condition` ENUM('New', 'Used') NOT NULL DEFAULT 'Used'");

// ✅ Self-healing script for the room_rules table
// 1. Check for and add 'admin_id' if it's missing.
$check_admin_id = $conn->query("SHOW COLUMNS FROM `room_rules` LIKE 'admin_id'");
if ($check_admin_id->num_rows == 0) {
    $conn->query("ALTER TABLE `room_rules` ADD `admin_id` INT NOT NULL AFTER `room_id`");
}
// 2. Check for and add 'type' if it's missing.
$check_type = $conn->query("SHOW COLUMNS FROM `room_rules` LIKE 'type'");
if ($check_type->num_rows == 0) {
    $conn->query("ALTER TABLE `room_rules` ADD `type` ENUM('rule') NOT NULL DEFAULT 'rule' AFTER `admin_id`");
}

// =============================
// Super Admin Tables
// =============================

// ✅ Create 'super_admins' table if it doesn't exist
$conn->query("
CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ Create 'subscription_plans' table
$conn->query("
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ Seed default plans if empty
$check_plans = $conn->query("SELECT id FROM subscription_plans LIMIT 1");
if ($check_plans->num_rows == 0) {
    $conn->query("INSERT INTO subscription_plans (name, price, duration_days, description) VALUES ('Standard Monthly', 500.00, 30, 'Standard monthly access')");
    $conn->query("INSERT INTO subscription_plans (name, price, duration_days, description) VALUES ('Premium Yearly', 5000.00, 365, 'Yearly access with discount')");
}

// ✅ Self-healing script for subscription_plans (add max_rooms and features)
$check_plan_max_rooms = $conn->query("SHOW COLUMNS FROM `subscription_plans` LIKE 'max_rooms'");
if ($check_plan_max_rooms->num_rows == 0) {
    $conn->query("ALTER TABLE `subscription_plans` ADD `max_rooms` INT DEFAULT 0 AFTER `price`");
}

$check_plan_features = $conn->query("SHOW COLUMNS FROM `subscription_plans` LIKE 'features'");
if ($check_plan_features->num_rows == 0) {
    $conn->query("ALTER TABLE `subscription_plans` ADD `features` TEXT NULL AFTER `description`");
}

// ✅ Create 'admin_subscriptions' table to track owner payments
$conn->query("
CREATE TABLE IF NOT EXISTS admin_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    plan VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ Self-healing script for admin_subscriptions table (add transaction_id)
$check_sub_txn = $conn->query("SHOW COLUMNS FROM `admin_subscriptions` LIKE 'transaction_id'");
if ($check_sub_txn->num_rows == 0) {
    $conn->query("ALTER TABLE `admin_subscriptions` ADD `transaction_id` VARCHAR(50) NULL DEFAULT NULL AFTER `status`");
}

// ✅ Create 'system_settings' table for global configurations
$conn->query("
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ Seed default payment settings if they don't exist
$default_settings = [
    'gcash_number' => '0912 345 6789',
    'gcash_name' => 'HouseMaster',
    'bank_name' => 'BDO',
    'bank_account_num' => '1234 5678 9012',
    'bank_account_name' => 'HouseMaster Inc.'
];

foreach ($default_settings as $key => $value) {
    $check_setting = $conn->query("SELECT id FROM system_settings WHERE setting_key = '$key'");
    if ($check_setting->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
}

// ✅ Seed the super_admins table with the default super admin if it's empty
$check_super_admin = $conn->query("SELECT id FROM super_admins LIMIT 1");
if ($check_super_admin->num_rows == 0) {
    $super_admin_user = SUPER_ADMIN_USER;
    $super_admin_email = "superadmin@example.com"; // Default email
    $super_admin_pass = password_hash(SUPER_ADMIN_PASS, PASSWORD_DEFAULT); // Hash the password

    $stmt = $conn->prepare("INSERT INTO super_admins (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $super_admin_user, $super_admin_email, $super_admin_pass);
    $stmt->execute();
}
?>
