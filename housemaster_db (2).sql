-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 06:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `housemaster_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `boarding_code` varchar(20) NOT NULL,
  `selected_plan_id` int(11) NOT NULL DEFAULT 1,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `account_status` enum('active','payment_due','restricted','pending') NOT NULL DEFAULT 'pending',
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `boarding_code`, `selected_plan_id`, `payment_proof`, `payment_method`, `account_status`, `reset_token`, `reset_token_expires_at`, `created_at`) VALUES
(34, 'Yan', 'ian.ybo@mdci.edu.ph', '$2y$10$NhS9OOpUVjIZTfk4LLkl0.jWZvVxuQXj2If7tC0g3pYddTUwMHCnq', '27D63C', 1, 'proof_34_1771089298.png', 'gcash', 'active', NULL, NULL, '2026-02-14 17:09:26'),
(35, 'ian', 'yboian577@gmail.com', '$2y$10$1yvfQNRSMTmQlzbcWMlm/OrXVgNfLaWIgRRVH7FHH0zRmerrLFIBW', 'F4FB66', 3, 'proof_35_1771989164.png', 'gcash', 'active', NULL, NULL, '2026-02-15 05:32:23');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscriptions`
--

CREATE TABLE `admin_subscriptions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `plan` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_subscriptions`
--

INSERT INTO `admin_subscriptions` (`id`, `admin_id`, `plan`, `start_date`, `end_date`, `status`, `transaction_id`, `created_at`, `updated_at`) VALUES
(9, 34, 'Standard Monthly', '2026-02-14', '2026-03-16', 'active', 'TXN-6990AF2A6BA34-20260214', '2026-02-14 17:21:46', '2026-02-14 17:21:46'),
(10, 35, 'Standard Monthly', '2026-02-25', '2026-03-27', 'active', 'TXN-699E68B3128A3-20260225', '2026-02-17 06:06:50', '2026-02-25 03:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `admin_id`, `title`, `amount`, `expense_date`, `description`, `created_at`) VALUES
(1, 34, 'internet', 1500.00, '2026-02-14', 'internet use', '2026-02-14 17:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_type` enum('tenant','admin','system') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sender_admin_id` int(11) DEFAULT NULL,
  `sender_tenant_id` int(11) DEFAULT NULL,
  `receiver_admin_id` int(11) DEFAULT NULL,
  `receiver_tenant_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_type`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`, `sender_admin_id`, `sender_tenant_id`, `receiver_admin_id`, `receiver_tenant_id`) VALUES
(108, 'system', 0, 33, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-6990A618733E5-20260214', 1, '2026-02-14 16:43:04', NULL, NULL, NULL, NULL),
(109, 'system', 0, 34, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-6990AF2A6BA34-20260214', 1, '2026-02-14 17:21:46', NULL, NULL, NULL, NULL),
(110, 'system', 0, 35, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-6994057A8C19A-20260217', 1, '2026-02-17 06:06:50', NULL, NULL, NULL, NULL),
(111, 'tenant', 63, 35, 'HI', 1, '2026-02-17 07:20:18', NULL, NULL, NULL, NULL),
(112, 'system', 0, 35, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-699E67CC557D3-20260225', 1, '2026-02-25 03:09:00', NULL, NULL, NULL, NULL),
(113, 'system', 0, 35, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-699E67F9731F1-20260225', 1, '2026-02-25 03:09:45', NULL, NULL, NULL, NULL),
(114, 'system', 0, 35, 'Congratulations! Your payment has been verified by the Super Admin. Your account is now active and valid for 30 days.\n\nTransaction ID: TXN-699E68B3128A3-20260225', 1, '2026-02-25 03:12:51', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `boarding_code` varchar(20) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`id`, `title`, `body`, `created_at`, `boarding_code`, `admin_id`) VALUES
(13, 'water interruption', 'march 18 at 9pm \r\n\r\n-Management', '2026-02-17 06:24:49', 'F4FB66', 35);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `boarding_code` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `payment_proof` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `admin_id`, `boarding_code`, `amount`, `due_date`, `status`, `payment_proof`, `created_at`) VALUES
(61, 62, 34, '27D63C', 1000.00, '2026-03-15', 'paid', NULL, '2026-02-14 17:41:30'),
(62, 62, 34, '27D63C', 1000.00, '2026-04-15', 'paid', NULL, '2026-02-14 17:56:54'),
(63, 62, 34, '27D63C', 1000.00, '2026-05-15', 'paid', 'proof_63_1771092326.png', '2026-02-14 18:03:48'),
(64, 63, 35, 'F4FB66', 2000.00, '2026-03-17', 'paid', NULL, '2026-02-17 06:09:23'),
(65, 63, 35, 'F4FB66', 2000.00, '2026-04-17', 'paid', NULL, '2026-02-17 12:05:32');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_label` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL,
  `rental_rate` decimal(10,2) NOT NULL,
  `notice` text DEFAULT NULL,
  `room_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `boarding_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_label`, `capacity`, `rental_rate`, `notice`, `room_code`, `created_at`, `boarding_code`) VALUES
(35, 'Dorm A - Room 2', 2, 1000.00, NULL, 'BE291091', '2026-02-14 17:40:59', '27D63C'),
(36, 'Dorm A - Room 1', 2, 2000.00, NULL, '9B114788', '2026-02-17 06:07:17', 'F4FB66');

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_filename`, `created_at`) VALUES
(22, 35, 'room_6990b5ff246223.10042050.jpg', '2026-02-14 17:50:55'),
(23, 36, 'room_69940caf2a05c5.93679747.webp', '2026-02-17 06:37:35'),
(24, 36, 'room_69940caf2ae7d0.15950099.webp', '2026-02-17 06:37:35'),
(25, 36, 'room_69940caf2b9696.27884860.jpg', '2026-02-17 06:37:35'),
(26, 36, 'room_69940caf2cb231.36449649.jpg', '2026-02-17 06:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `room_items`
--

CREATE TABLE `room_items` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `condition` enum('New','Used') NOT NULL DEFAULT 'Used',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_items`
--

INSERT INTO `room_items` (`id`, `room_id`, `admin_id`, `item_name`, `quantity`, `condition`, `created_at`) VALUES
(15, 36, 35, 'bed', 2, 'New', '2026-02-17 06:22:24');

-- --------------------------------------------------------

--
-- Table structure for table `room_rules`
--

CREATE TABLE `room_rules` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `type` enum('rule') NOT NULL DEFAULT 'rule',
  `rule_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_rules`
--

INSERT INTO `room_rules` (`id`, `room_id`, `admin_id`, `type`, `rule_text`, `created_at`) VALUES
(9, 36, 35, 'rule', 'no visitor alllowed', '2026-02-17 06:22:35');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `max_rooms` int(11) DEFAULT 0,
  `duration_days` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `price`, `max_rooms`, `duration_days`, `description`, `features`, `created_at`) VALUES
(1, 'Standard Monthly', 500.00, 0, 30, 'Standard monthly access', NULL, '2026-02-14 16:58:09'),
(3, 'Standard Monthly', 130.00, 10, 30, 'Standard monthly access,\r\nGood for managing 10 Rooms', '', '2026-02-25 03:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `username`, `password`, `email`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$2d6q8If7dGI3/OsSj89KhO9Smxjbd1rwAzJB927EnwbhAIHzbH6J.', 'superadmin@housemaster.com', '2025-12-16 12:53:50');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'gcash_number', '0912 345 6789', '2026-02-14 17:28:09'),
(2, 'gcash_name', 'HouseMaster', '2026-02-14 17:28:09'),
(3, 'bank_name', 'BDO', '2026-02-14 17:28:09'),
(4, 'bank_account_num', '1234 5678 9012', '2026-02-14 17:28:09'),
(5, 'bank_account_name', 'HouseMaster Inc.', '2026-02-14 17:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `system_state`
--

CREATE TABLE `system_state` (
  `state_key` varchar(50) NOT NULL,
  `state_value` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_state`
--

INSERT INTO `system_state` (`state_key`, `state_value`) VALUES
('last_update_timestamp', 1771329932);

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `start_boarding_date` date DEFAULT NULL,
  `emergency_contact_person` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `status` enum('active','pending','inactive','unassigned') DEFAULT 'unassigned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `boarding_code` varchar(20) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `requested_room_id` int(11) DEFAULT NULL,
  `last_announcement_view` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `fullname`, `email`, `password`, `age`, `phone`, `start_boarding_date`, `emergency_contact_person`, `emergency_contact_phone`, `reset_token`, `reset_token_expires_at`, `status`, `created_at`, `boarding_code`, `admin_id`, `requested_room_id`, `last_announcement_view`) VALUES
(62, 'kim222', 'kimesogan736@gmail.com', '$2y$10$Qt/j4bEsPuEOq1.4AbShxuKq4YNiblcesjpBMQ/JZLSfUwah9TSEy', 32, '09677698238', '2026-02-15', 'me', '09677698238', NULL, NULL, 'active', '2026-02-14 17:41:12', '27D63C', 34, 35, NULL),
(63, 'yan', 'ian@mdci.edu.ph', '$2y$10$TIJUuMGESvwjwcC3DZDWxeD5ROjW5awRBGoeHLClR9xilO4YjLZ7O', 23, '09677698238', '2026-02-17', 'me', '09677698238', NULL, NULL, 'active', '2026-02-17 06:08:49', 'F4FB66', 35, 36, NULL),
(64, 'Ian Ybo', 'ianybo3000@gmail.com', '$2y$10$Er.Z.0VO/B3YQc5QemBhBusgTcYwO.d.NJdB6pbNlWaAjJ7kp9aZi', 23, '09923542404', NULL, 'me', '0953532746', NULL, NULL, 'pending', '2026-02-24 07:09:05', 'F4FB66', 35, 36, NULL),
(65, 'Ian Ybo', 'yboian576@gmail.com', '$2y$10$OJmGMhxtQa9rmJXU.5RmI.Nlcg22QpOpf19vN7fLh35xxcEzebxiG', 23, '09923542404', NULL, 'me', '0953532746', NULL, NULL, 'pending', '2026-02-24 07:35:35', 'F4FB66', 35, 36, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_rooms`
--

CREATE TABLE `tenant_rooms` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant_rooms`
--

INSERT INTO `tenant_rooms` (`id`, `tenant_id`, `room_id`, `assigned_at`) VALUES
(60, 62, 35, '2026-02-14 17:41:30'),
(61, 63, 36, '2026-02-17 06:09:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `boarding_code` (`boarding_code`);

--
-- Indexes for table `admin_subscriptions`
--
ALTER TABLE `admin_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_messages_sender_admin` (`sender_admin_id`),
  ADD KEY `fk_messages_sender_tenant` (`sender_tenant_id`),
  ADD KEY `fk_messages_receiver_admin` (`receiver_admin_id`),
  ADD KEY `fk_messages_receiver_tenant` (`receiver_tenant_id`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notice_admin` (`admin_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `fk_payments_admin` (`admin_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_code` (`room_code`),
  ADD UNIQUE KEY `room_label_boarding_code` (`room_label`,`boarding_code`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `room_items`
--
ALTER TABLE `room_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `room_rules`
--
ALTER TABLE `room_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_state`
--
ALTER TABLE `system_state`
  ADD PRIMARY KEY (`state_key`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_tenants_admin` (`admin_id`),
  ADD KEY `fk_tenants_requested_room` (`requested_room_id`);

--
-- Indexes for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `admin_subscriptions`
--
ALTER TABLE `admin_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `room_items`
--
ALTER TABLE `room_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `room_rules`
--
ALTER TABLE `room_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_subscriptions`
--
ALTER TABLE `admin_subscriptions`
  ADD CONSTRAINT `admin_subscriptions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_receiver_admin` FOREIGN KEY (`receiver_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_receiver_tenant` FOREIGN KEY (`receiver_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender_admin` FOREIGN KEY (`sender_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender_tenant` FOREIGN KEY (`sender_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `fk_notice_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_items`
--
ALTER TABLE `room_items`
  ADD CONSTRAINT `room_items_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_rules`
--
ALTER TABLE `room_rules`
  ADD CONSTRAINT `room_rules_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenants_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenants_requested_room` FOREIGN KEY (`requested_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  ADD CONSTRAINT `tenant_rooms_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenant_rooms_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
