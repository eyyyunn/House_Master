-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 04:51 PM
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
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `boarding_code`, `reset_token`, `reset_token_expires_at`, `created_at`) VALUES
(3, 'ian', 'ian@gm.com', '$2y$10$4UR87Rbl6RsPzLxYIu62tuLzAAQtE7leSBqdMgpP7kAlgi.ipKCK.', '29BF05', NULL, NULL, '2025-11-10 17:32:40'),
(4, 'ian', 'yboian577@gmail.com', '$2y$10$GiVBePcX4c0SpvRtZxKpbOUPVRGQiqG1FQDjsYmEryThobWsMq9eC', 'F8B8B8', NULL, NULL, '2025-11-11 11:38:14'),
(6, 'ian22', 'ian.ybo@mdci.edu.ph', '$2y$10$AfI2cMwiPuhEXzCQtWbMEe.NrmCaBxf9GDA9okfrEAEF2PFJVGN5W', '9237DA', NULL, NULL, '2025-11-11 11:52:16');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_type` enum('tenant','admin') NOT NULL,
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
(15, 'tenant', 14, 2, 'hello', 0, '2025-10-27 12:20:00', NULL, NULL, NULL, NULL),
(16, 'admin', 2, 14, 'Hi Ian Ybo, this is a reminder for your payment of ₱2,000 due on Nov 27, 2025. Thank you. - HouseMaster', 1, '2025-10-27 12:20:50', NULL, NULL, NULL, NULL),
(17, 'admin', 2, 18, 'Hi Ian Ybo, this is a reminder for your payment of ₱2,000 due on Nov 21, 2025. Thank you. - HouseMaster', 1, '2025-10-27 13:39:39', NULL, NULL, NULL, NULL),
(18, 'admin', 2, 18, 'hello', 0, '2025-11-06 13:53:13', NULL, NULL, NULL, NULL),
(19, 'tenant', 21, 2, 'hello', 0, '2025-11-06 13:53:28', NULL, NULL, NULL, NULL),
(20, 'admin', 2, 21, 'Hi kimverly T. Esogan, this is a reminder for your payment of ₱2,000 due on Dec 06, 2025. Thank you. - HouseMaster', 1, '2025-11-06 13:58:00', NULL, NULL, NULL, NULL),
(21, 'admin', 2, 21, 'Hi kimverly T. Esogan, this is a reminder for your payment of ₱2,000 due on Dec 06, 2025. Thank you. - HouseMaster', 1, '2025-11-06 13:58:22', NULL, NULL, NULL, NULL),
(22, 'tenant', 21, 2, 'hello', 0, '2025-11-06 14:15:35', NULL, NULL, NULL, NULL),
(23, 'admin', 2, 21, 'okay', 1, '2025-11-06 14:15:43', NULL, NULL, NULL, NULL),
(24, 'admin', 2, 21, 's', 1, '2025-11-06 14:15:55', NULL, NULL, NULL, NULL),
(25, 'admin', 2, 21, 's', 1, '2025-11-06 14:22:55', NULL, NULL, NULL, NULL),
(26, 'admin', 2, 21, 'sa', 1, '2025-11-06 14:23:12', NULL, NULL, NULL, NULL),
(27, 'tenant', 21, 2, 's', 0, '2025-11-06 14:23:27', NULL, NULL, NULL, NULL),
(28, 'admin', 2, 21, 'a', 1, '2025-11-06 14:30:48', NULL, NULL, NULL, NULL),
(29, 'admin', 2, 21, 'a', 1, '2025-11-06 14:31:02', NULL, NULL, NULL, NULL),
(30, 'tenant', 21, 2, 'cx', 0, '2025-11-06 14:40:40', NULL, NULL, NULL, NULL),
(31, 'admin', 2, 21, 's', 1, '2025-11-06 14:40:58', NULL, NULL, NULL, NULL),
(32, 'admin', 2, 21, 'ss', 1, '2025-11-06 14:42:13', NULL, NULL, NULL, NULL),
(33, 'tenant', 21, 2, 'asd', 0, '2025-11-06 14:42:25', NULL, NULL, NULL, NULL),
(34, 'admin', 2, 21, 'aa', 1, '2025-11-06 14:48:53', NULL, NULL, NULL, NULL),
(35, 'admin', 2, 21, 'a', 1, '2025-11-06 14:49:10', NULL, NULL, NULL, NULL),
(36, 'tenant', 21, 2, 'a', 0, '2025-11-06 14:49:19', NULL, NULL, NULL, NULL),
(37, 'admin', 2, 21, 'e', 1, '2025-11-06 14:57:34', NULL, NULL, NULL, NULL),
(38, 'tenant', 21, 2, 'd', 0, '2025-11-06 14:57:48', NULL, NULL, NULL, NULL),
(39, 'tenant', 21, 2, 'hello', 0, '2025-11-06 14:58:02', NULL, NULL, NULL, NULL),
(40, 'tenant', 21, 2, 'asa', 0, '2025-11-06 15:03:20', NULL, NULL, NULL, NULL),
(41, 'admin', 2, 21, 'a', 1, '2025-11-06 15:03:47', NULL, NULL, NULL, NULL),
(42, 'admin', 2, 18, 'a', 0, '2025-11-06 15:04:01', NULL, NULL, NULL, NULL),
(43, 'tenant', 21, 2, 'a', 0, '2025-11-06 15:04:07', NULL, NULL, NULL, NULL),
(44, 'tenant', 21, 2, 'asasa', 0, '2025-11-06 15:05:14', NULL, NULL, NULL, NULL),
(45, 'tenant', 21, 2, 'asdasda', 0, '2025-11-06 15:08:28', NULL, NULL, NULL, NULL),
(46, 'admin', 2, 21, 'lak', 1, '2025-11-06 15:10:42', NULL, NULL, NULL, NULL),
(47, 'tenant', 21, 2, 'kjk', 0, '2025-11-06 15:12:02', NULL, NULL, NULL, NULL),
(48, 'admin', 2, 21, 'as', 0, '2025-11-06 15:12:12', NULL, NULL, NULL, NULL),
(49, 'tenant', 22, 2, 'hello', 0, '2025-11-08 03:15:03', NULL, NULL, NULL, NULL),
(50, 'tenant', 22, 2, 'lk', 0, '2025-11-08 03:15:20', NULL, NULL, NULL, NULL),
(51, 'admin', 2, 22, 'kk', 1, '2025-11-08 03:15:29', NULL, NULL, NULL, NULL),
(52, 'admin', 2, 22, 'Hi Ian Ybo, this is a reminder for your payment of ₱2,000 due on Dec 06, 2025. Thank you. - HouseMaster', 1, '2025-11-08 03:15:49', NULL, NULL, NULL, NULL),
(53, 'tenant', 24, 2, 'bugsss', 0, '2025-11-08 04:01:06', NULL, NULL, NULL, NULL),
(54, 'tenant', 37, 3, 'yow', 1, '2025-11-10 17:34:48', NULL, NULL, NULL, NULL),
(55, 'tenant', 37, 3, 'heyy', 1, '2025-11-10 18:53:10', NULL, NULL, NULL, NULL),
(56, 'admin', 3, 37, 'ow', 1, '2025-11-10 18:53:25', NULL, NULL, NULL, NULL),
(57, 'admin', 3, 37, 'k', 1, '2025-11-10 18:53:39', NULL, NULL, NULL, NULL),
(58, 'tenant', 37, 3, 'hello', 1, '2025-11-11 05:22:30', NULL, NULL, NULL, NULL),
(59, 'tenant', 37, 3, 'ol', 1, '2025-11-11 06:27:46', NULL, NULL, NULL, NULL),
(60, 'tenant', 38, 6, 'hello', 1, '2025-11-11 11:56:22', NULL, NULL, NULL, NULL),
(61, 'admin', 6, 38, 'okay', 1, '2025-11-11 11:56:40', NULL, NULL, NULL, NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `admin_id`, `boarding_code`, `amount`, `due_date`, `status`, `created_at`) VALUES
(33, 37, 3, '29BF05', 2000.00, '2025-12-11', 'pending', '2025-11-10 17:34:05'),
(34, 38, 6, '9237DA', 2000.00, '2025-12-06', 'paid', '2025-11-11 11:53:18');

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
(1, 'Dorm B - Room 1', 2, 2000.01, NULL, '7CB06505', '2025-10-15 15:00:35', 'CAFE16'),
(2, 'Dorm A - Room 2', 3, 2000.00, NULL, 'E316BAAF', '2025-10-27 02:19:33', '9EC9CC'),
(3, 'Dorm A - Room 6', 2, 0.03, NULL, '6D9B91E6', '2025-11-08 05:55:23', '9EC9CC'),
(4, 'new', 22, 22.00, NULL, '7F44D9F5', '2025-11-10 17:29:39', '9EC9CC'),
(5, 'Dorm B', 2, 2000.00, NULL, '07B7CD78', '2025-11-10 17:33:54', '29BF05'),
(7, 'Dorm m - Room 6', 2, 1000.00, NULL, '04618C22', '2025-11-11 05:23:18', '29BF05'),
(9, 'new1', 2, 2000.00, NULL, '423C92BE', '2025-11-11 05:23:45', '29BF05'),
(10, 'Dorm A - Room 1', 2, 2000.00, NULL, '782545F5', '2025-11-11 11:48:31', 'E45CDC'),
(11, 'Dorm B - Room 2', 2, 2999.00, NULL, 'C70419A6', '2025-11-11 11:51:06', 'E45CDC'),
(12, 'Board room', 2, 2000.00, NULL, '18A6D44E', '2025-11-11 11:52:38', '9237DA'),
(13, 'board 2', 2, 199.00, NULL, '141F3AF5', '2025-11-11 11:55:26', '9237DA'),
(14, 'board 3', 6, 199.00, NULL, '33FDE9C0', '2025-11-11 11:55:38', '9237DA');

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
(1, 1, 1, 'bed', 1, 'Used', '2025-10-15 16:50:38'),
(2, 1, 1, 'aircon', 1, 'New', '2025-10-15 18:30:46'),
(3, 2, 2, 'bed', 1, 'New', '2025-10-27 11:53:15'),
(4, 2, 2, 'aircon', 2, 'New', '2025-11-08 04:13:57'),
(5, 2, 2, 'TABLE', 5, 'New', '2025-11-08 04:14:18');

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
(1, 1, 1, 'rule', 'no visitor alllowed', '2025-10-15 17:00:48'),
(2, 1, 1, 'rule', 'no visitor alllowed', '2025-10-15 18:31:12'),
(3, 2, 2, 'rule', 'no visitor alllowed', '2025-11-08 04:18:27');

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
('last_update_timestamp', 1762862200);

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
(37, 'JAY', 'ian@new.com', '$2y$10$lOECfgOVjGNTFPaHMOpp0.mErppeCFIfYHhvw2H4DAvQAKIZCTJAS', 22, '09923542404', '2025-11-11', 'me', '0953532746', NULL, NULL, 'active', '2025-11-10 17:34:00', '29BF05', 3, 5, '2025-11-10 18:58:48'),
(38, 'ian', 'ianybo3000@gmail.com', '$2y$10$f7X.t/nd7Kv.au3QUNHy8.Mtg.qlcAZjxNQLYNMsSPsq27ikyQcwi', 22, '09923542404', '2025-11-06', 'ian', '0953532746', NULL, NULL, 'active', '2025-11-11 11:52:47', '9237DA', 6, 12, NULL);

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
(35, 37, 5, '2025-11-10 17:34:05'),
(36, 38, 12, '2025-11-11 11:53:18');

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
  ADD UNIQUE KEY `room_label` (`room_label`),
  ADD UNIQUE KEY `room_code` (`room_code`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `room_items`
--
ALTER TABLE `room_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `room_rules`
--
ALTER TABLE `room_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

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
