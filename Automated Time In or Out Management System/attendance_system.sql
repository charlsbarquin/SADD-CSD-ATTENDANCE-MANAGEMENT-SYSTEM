-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3308
-- Generation Time: Apr 27, 2025 at 04:39 PM
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
-- Database: `attendance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `last_failed_attempt_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `created_at`, `login_attempts`, `last_attempt`, `account_locked`, `lock_until`, `last_failed_attempt_ip`) VALUES
(3, 'admin', '$2y$10$jl/sAqn1Faery8LpnSYWqerEP76ah6/YZiBl2uTSva1LZfP99AgrG', '', '2025-03-31 08:09:18', 0, NULL, 0, NULL, '127.0.0.1');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `target_id`, `created_at`) VALUES
(1, 3, 'Approved professor ID 1', 1, '2025-04-01 07:55:30'),
(2, 3, 'Approved professor ID 2', 2, '2025-04-01 10:20:07'),
(3, 3, 'Approved professor ID 3', 3, '2025-04-01 10:38:31'),
(4, 3, 'Approved professor ID 4', 4, '2025-04-03 06:10:31'),
(5, 3, 'Approved professor ID 5', 5, '2025-04-03 06:41:12'),
(6, 3, 'Approved professor ID 32 as active', 32, '2025-04-11 13:29:57'),
(7, 3, 'Approved professor ID 6 as active', 6, '2025-04-12 05:26:20'),
(8, 3, 'Approved professor ID 33 as active', 33, '2025-04-12 13:22:40'),
(9, 3, 'Approved professor ID 34 as active', 34, '2025-04-12 16:05:45'),
(10, 3, 'Approved professor ID 35 as active', 35, '2025-04-13 15:25:51'),
(11, 3, 'Approved professor ID 37 as active', 37, '2025-04-17 02:22:30'),
(12, 3, 'Approved professor ID 38 as active', 38, '2025-04-17 02:24:51'),
(13, 3, 'Approved professor ID 7 as active', 7, '2025-04-17 02:31:34'),
(14, 3, 'Approved professor ID 42 as active', 42, '2025-04-24 03:03:50'),
(15, 3, 'Approved professor ID 43 as active', 43, '2025-04-26 02:01:44'),
(16, 3, 'Approved professor ID 8 as active', 8, '2025-04-26 02:09:16'),
(17, 3, 'Approved professor ID 9 as active', 9, '2025-04-26 02:09:46'),
(18, 3, 'Approved professor ID 10 as active', 10, '2025-04-26 02:20:54'),
(19, 3, 'Approved professor ID 11 as active', 11, '2025-04-26 02:31:32'),
(20, 3, 'Approved professor ID 11 as active', 11, '2025-04-26 02:31:41'),
(21, 3, 'Approved professor ID 12 as active', 12, '2025-04-26 02:56:41'),
(22, 3, 'Approved professor ID 44 as active', 44, '2025-04-26 02:57:58'),
(23, 3, 'Approved professor ID 45 as active', 45, '2025-04-26 03:07:05'),
(24, 3, 'Approved professor ID 46 as active', 46, '2025-04-27 09:09:04'),
(25, 3, 'Approved professor ID 47 as active', 47, '2025-04-27 10:09:28'),
(26, 3, 'Approved professor ID 48 as active', 48, '2025-04-27 10:20:20'),
(27, 3, 'Approved professor ID 49 as active', 49, '2025-04-27 12:25:45'),
(28, 3, 'Approved professor ID 50 as active', 50, '2025-04-27 12:29:32');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `am_check_in` datetime DEFAULT NULL,
  `am_check_out` datetime DEFAULT NULL,
  `pm_check_in` datetime DEFAULT NULL,
  `pm_check_out` datetime DEFAULT NULL,
  `status` enum('present','absent','half-day') DEFAULT 'absent',
  `am_face_scan_image` varchar(255) DEFAULT NULL,
  `pm_face_scan_image` varchar(255) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checkin_date` date NOT NULL DEFAULT curdate(),
  `am_latitude` decimal(10,8) DEFAULT NULL,
  `am_longitude` decimal(11,8) DEFAULT NULL,
  `pm_latitude` decimal(10,8) DEFAULT NULL,
  `pm_longitude` decimal(11,8) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `work_duration` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `user` varchar(100) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `logs`
--
DELIMITER $$
CREATE TRIGGER `enforce_log_terms` BEFORE INSERT ON `logs` FOR EACH ROW BEGIN
    SET NEW.action = REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(NEW.action, 'check-in', 'time-in'),
                'check-out', 'time-out'),
            'checked in', 'timed in'),
        'checked out', 'timed out');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'time-in or time-out',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `notifications`
--
DELIMITER $$
CREATE TRIGGER `enforce_time_terms` BEFORE INSERT ON `notifications` FOR EACH ROW BEGIN
    SET NEW.message = REPLACE(
        REPLACE(NEW.message, 'checked in', 'timed in'),
        'checked out',
        'timed out'
    );
    SET NEW.type = REPLACE(
        REPLACE(NEW.type, 'check-in', 'time-in'),
        'check-out',
        'time-out'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `professors`
--

CREATE TABLE `professors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','pending') DEFAULT 'pending',
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `last_failed_attempt_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professors`
--

INSERT INTO `professors` (`id`, `name`, `email`, `designation`, `profile_image`, `created_at`, `status`, `approved_at`, `approved_by`, `department`, `phone`, `username`, `password`, `login_attempts`, `last_attempt`, `account_locked`, `lock_until`, `last_failed_attempt_ip`) VALUES
(1, 'Arnold B. Platon', 'johndoe@bup.edu.ph', 'Department Head', '', '2025-02-08 05:22:23', 'active', '2025-04-01 15:55:30', 3, 'Computer Studies Department', '0917283016', NULL, NULL, 0, NULL, 0, NULL, NULL),
(2, 'Vince Angelo E. Naz ', 'janesmith@bup.edu.ph', 'BSIT Program Coordinator', '', '2025-02-08 05:22:23', 'active', '2025-04-01 18:20:07', 3, 'Computer Studies Department', '0950475140', NULL, NULL, 0, NULL, 0, NULL, NULL),
(3, 'Jerry B. Agsunod', 'markdelacruz@bup.edu.ph', 'BSCS Program Coordinator', '', '2025-02-08 05:22:23', 'active', '2025-04-01 18:38:31', 3, 'Computer Studies Department', '0900526661', NULL, NULL, 0, NULL, 0, NULL, NULL),
(4, 'Paulo L. Perete', 'mariasantos@bup.edu.ph', 'BSIT-Animation Program Coordinator', '', '2025-02-08 06:17:30', 'active', '2025-04-03 14:10:31', 3, 'Computer Studies Department', '0951207890', NULL, NULL, 0, NULL, 0, NULL, NULL),
(5, 'Guillermo V. Red, Jr.', 'rafaelcruz@bup.edu.ph', 'BSIS Program Coordinator', '', '2025-02-08 06:17:30', 'active', '2025-04-03 14:41:12', 3, 'Computer Studies Department', '0954459468', NULL, NULL, 0, NULL, 0, NULL, NULL),
(6, 'Maria Charmy A. Arispe', 'angelareyes@bup.edu.ph', 'College IMO Coordinator', '', '2025-02-08 06:17:30', 'active', '2025-04-12 13:26:20', 3, 'Computer Studies Department', '0918673672', NULL, NULL, 0, NULL, 0, NULL, NULL),
(7, 'Blessica B. Dorosan', 'michaeltan@bup.edu.ph', 'Professor', '', '2025-02-08 06:17:30', 'active', '2025-04-17 10:31:34', 3, 'Computer Studies Department', '0929989963', NULL, NULL, 0, NULL, 0, NULL, NULL),
(8, 'Suzanne S. Causapin', 'sophiagomez@bup.edu.ph', 'Professor', '', '2025-02-08 06:17:30', 'active', '2025-04-26 10:09:16', 3, 'Computer Studies Department', '0993928801', NULL, NULL, 0, NULL, 0, NULL, NULL),
(9, 'Khristine A. Botin', 'carlosvillanueva@bup.edu.ph', 'Professor', '', '2025-02-08 06:17:30', 'active', '2025-04-26 10:09:46', 3, 'Computer Studies Department', '0979674122', NULL, NULL, 0, NULL, 0, NULL, NULL),
(10, 'Jorge Sulipicio S. Aganan', 'jessicalim@bup.edu.ph', 'Professor', '', '2025-02-08 06:17:30', 'active', '2025-04-26 10:20:54', 3, 'Computer Studies Department', '0916584207', NULL, NULL, 0, NULL, 0, NULL, NULL),
(11, 'Joseph L. Carinan', 'benedictchua@bup.edu.ph', 'College Document Custodian', '', '2025-02-08 06:17:30', 'active', '2025-04-26 10:31:41', 3, 'Computer Studies Department', '0943898675', NULL, NULL, 0, NULL, 0, NULL, NULL),
(12, 'Mary Antoniette S. Ariño', 'oliviamendoza@bup.edu.ph', 'College SIP Coordinator', '', '2025-02-08 06:17:30', 'active', '2025-04-26 10:56:41', 3, 'Computer Studies Department', '0969740758', NULL, NULL, 0, NULL, 0, NULL, NULL),
(33, 'Charls Emil Barquin', 'charlsbarquin2@gmail.com', 'Professor', NULL, '2025-04-12 13:22:27', 'active', '2025-04-12 21:22:40', 3, 'Computer Studies Department', '09386090970', 'Charls', '$2y$10$uKWrAchzev5nCR0zHPJLs.X3jaAfTXZNWArp39rRq4tPwbGkDWfKW', 0, NULL, 0, NULL, NULL);

--
-- Triggers `professors`
--
DELIMITER $$
CREATE TRIGGER `after_professor_insert` AFTER INSERT ON `professors` FOR EACH ROW BEGIN
    INSERT INTO logs (action, user, timestamp)
    VALUES (CONCAT('New professor registered: ', NEW.name), 'system', NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `security_policies`
--

CREATE TABLE `security_policies` (
  `id` int(11) NOT NULL,
  `policy_name` varchar(100) NOT NULL,
  `policy_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_policies`
--

INSERT INTO `security_policies` (`id`, `policy_name`, `policy_value`, `description`) VALUES
(1, 'max_login_attempts', '5', 'Maximum allowed failed login attempts before lockout'),
(2, 'account_lock_duration', '30', 'Lockout duration in minutes');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `late_cutoff` time NOT NULL DEFAULT '08:00:00',
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Manila',
  `am_cutoff` time DEFAULT '12:00:00',
  `pm_cutoff` time DEFAULT '17:00:00',
  `pm_late_cutoff` time DEFAULT '13:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `late_cutoff`, `timezone`, `am_cutoff`, `pm_cutoff`, `pm_late_cutoff`) VALUES
(1, '08:00:00', 'Asia/Manila', '12:00:00', '17:00:00', '13:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user`,`is_read`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_created` (`type`,`created_at`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `professors`
--
ALTER TABLE `professors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_professors_approved_by` (`approved_by`);

--
-- Indexes for table `security_policies`
--
ALTER TABLE `security_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policy_name` (`policy_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=397;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `professors`
--
ALTER TABLE `professors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `security_policies`
--
ALTER TABLE `security_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`);

--
-- Constraints for table `professors`
--
ALTER TABLE `professors`
  ADD CONSTRAINT `fk_professors_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
