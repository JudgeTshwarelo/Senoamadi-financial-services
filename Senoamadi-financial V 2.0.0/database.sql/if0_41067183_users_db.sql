-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql206.infinityfree.com
-- Generation Time: Sep 23, 2026 at 05:13 PM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41067183_users_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(60) NOT NULL,
  `target_type` varchar(40) NOT NULL,
  `target_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(12, 20, 'feedback_close', 'feedback', 6, '', '2026-09-22 23:43:34'),
(13, 20, 'feedback_resolve', 'feedback', 6, '', '2026-09-23 10:25:34'),
(14, 20, 'loan_approve', 'personal_loan', 2, '', '2026-09-23 21:01:53'),
(15, 20, 'feedback_review', 'feedback', 7, '', '2026-09-23 21:02:08'),
(16, 20, 'feedback_review', 'feedback', 7, '', '2026-09-23 21:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `admin_member_notes`
--

CREATE TABLE `admin_member_notes` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `flag` enum('none','watch','vip','risky','blocked') NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_projects`
--

CREATE TABLE `admin_projects` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `project_type` enum('personal','business','community','charity','other') NOT NULL DEFAULT 'personal',
  `goal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `raised_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expected_roi` decimal(5,2) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('planning','active','funded','paused','completed','cancelled') NOT NULL DEFAULT 'planning',
  `visibility` enum('private','members','public') NOT NULL DEFAULT 'private',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_savings`
--

CREATE TABLE `admin_savings` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `label` varchar(150) NOT NULL,
  `category` enum('savings','investment','asset','liability','other') NOT NULL DEFAULT 'savings',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','matured','closed','paused') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approved_loans`
--

CREATE TABLE `approved_loans` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` enum('suggestion','complaint','improvement','bug','feature','other') NOT NULL DEFAULT 'suggestion',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('new','in_review','resolved','closed') NOT NULL DEFAULT 'new',
  `admin_reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `replied_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `category`, `priority`, `subject`, `message`, `attachment`, `is_anonymous`, `status`, `admin_reply`, `replied_at`, `replied_by`, `created_at`, `updated_at`) VALUES
(6, 25, 'other', 'medium', 'Judge', 'I\'m asking for a loan ranges between R2m to R2.5m', NULL, 1, 'resolved', NULL, '2026-09-23 03:25:34', 'JUDGE TSHWARELO SENOAMADI', '2026-09-22 23:21:06', '2026-09-23 10:25:34'),
(7, 24, 'suggestion', 'medium', 'Personal Page', 'Personal page ain\'t working cant post anything', NULL, 1, 'in_review', NULL, '2026-09-23 14:06:37', 'JUDGE TSHWARELO SENOAMADI', '2026-09-23 20:45:45', '2026-09-23 21:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `plan` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `interest_rate` double DEFAULT 0,
  `last_interest` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_investments`
--

CREATE TABLE `personal_investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expected_roi` decimal(5,2) DEFAULT 0.00,
  `duration_months` int(11) DEFAULT 0,
  `visibility` enum('public','private') NOT NULL DEFAULT 'private',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `personal_investments`
--

INSERT INTO `personal_investments` (`id`, `user_id`, `title`, `description`, `category`, `amount`, `expected_roi`, `duration_months`, `visibility`, `status`, `created_at`, `updated_at`, `admin_note`, `reviewed_at`, `reviewed_by`) VALUES
(2, 1, 'Solar Farm Phase 2', 'Expanding our solar farm in Limpopo. Seeking investors for the second phase.', 'Renewable Energy', '250000.00', '15.00', 24, 'public', 'approved', '2026-09-23 20:38:58', '2026-09-23 20:38:58', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `personal_loans`
--

CREATE TABLE `personal_loans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_repayable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `purpose` text DEFAULT NULL,
  `term_months` int(11) NOT NULL DEFAULT 0,
  `visibility` enum('public','private') NOT NULL DEFAULT 'private',
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `personal_loans`
--

INSERT INTO `personal_loans` (`id`, `user_id`, `principal`, `interest_rate`, `interest_amount`, `total_repayable`, `purpose`, `term_months`, `visibility`, `status`, `created_at`, `updated_at`, `admin_note`, `reviewed_at`, `reviewed_by`) VALUES
(2, 1, '100.00', '25.00', '25.00', '125.00', 'Business stock purchase', 6, 'public', 'approved', '2026-09-23 20:38:58', '2026-09-23 21:01:53', NULL, '2026-09-23 14:01:53', 20);

-- --------------------------------------------------------

--
-- Table structure for table `personal_proposals`
--

CREATE TABLE `personal_proposals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('funding','partnership','investment') NOT NULL DEFAULT 'funding',
  `amount_needed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `contact_link` varchar(255) DEFAULT NULL,
  `contact_whatsapp` varchar(20) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `contact_telegram` varchar(100) DEFAULT NULL,
  `business_plan` varchar(255) DEFAULT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'private',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `personal_proposals`
--

INSERT INTO `personal_proposals` (`id`, `user_id`, `title`, `description`, `type`, `amount_needed`, `contact_link`, `contact_whatsapp`, `contact_email`, `contact_telegram`, `business_plan`, `visibility`, `status`, `created_at`, `updated_at`, `admin_note`, `reviewed_at`, `reviewed_by`) VALUES
(3, 1, 'Senoamadi Farming Co-op', 'Community farming project seeking funding to buy seeds and equipment.', 'funding', '50000.00', NULL, '0721448175', 'baloyijudge@gmail.com', NULL, NULL, 'public', 'approved', '2026-09-23 20:38:58', '2026-09-23 20:38:58', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `type` enum('funding','proposal','investment') NOT NULL DEFAULT 'funding',
  `amount_needed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `contact_link` varchar(255) DEFAULT NULL,
  `contact_whatsapp` varchar(30) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `contact_telegram` varchar(100) DEFAULT NULL,
  `business_plan` varchar(255) DEFAULT NULL,
  `status` enum('active','pending','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `admin_status` enum('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_card` varchar(20) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `receiver_card` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `type` enum('internal','card','bank','mobile') DEFAULT 'internal',
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `firebase_uid` varchar(128) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `profile_pic`, `created_at`, `firebase_uid`, `email_verified`) VALUES
(19, 'Judge Tshwarelo', 'senoamadijudgetshwarelo@gmail.com', '+27721448175', '$2y$10$dOeoZ9tzFYh89N75fjRI7eJLYvN/ihxFIZxR2PMfgwnoMYwqtp5rW', 'user', 'uploads/1790118962_ChatGPT Image Jun 5, 2026, 01_17_00 PM.png', '2026-09-11 21:06:41', 'OB1DIroLgaZzrPH8Wtf7VMabUZq2', 1),
(20, 'JUDGE TSHWARELO SENOAMADI', 'baloyijudge@gmail.com', '+27721448175', '$2y$10$.WbPsJJZHekZ/4QVS4Mr4OViX9KG6Q2MJxTPqc7y1bFtYOzPAnThu', 'admin', 'uploads/1790021434_IMG-20260806-WA0035.jpg', '2026-09-12 13:09:24', 'wNRR3x7UIEUkZXobaMIkfuqoHzE3', 1),
(22, 'JUDGE TSHWARELO', 'judgetshwarelosenoamadi@gmail.com', '+27721448175', '$2y$12$uqoM0THvnVWFeQuYbK7rdObzG9.Bcnxvvsjla5lLFF/9YzFetFD5m', 'user', NULL, '2026-09-22 09:16:52', 'FO1I51hUfRgMhxui8Ovk3qfNd9Z2', 0),
(24, 'SENOAMADI GENESIS FARM', 'senoamadigenesisfarm@gmail.com', '+27072144817', '$2y$12$fofiLUOIo.ih./BZcIBpNuhuA515SAiDhAJyhVX5NI.BqRg3zoxzi', 'user', 'uploads/1790108026_ChatGPT Image Apr 1, 2026, 02_15_49 PM.png', '2026-09-22 12:32:45', 'onz5qdcgLeTOILY2kVdxBE7z5mx1', 1),
(25, 'THORISO', 'tauthoriso556@gmail.com', '+27649740403', '$2y$12$zM4NWHWD8Q0W1PRfuD/HHuzhhzZkvygKppV0//x70bQ9XeXv/X/Xy', 'user', NULL, '2026-09-22 23:10:25', 'Kqr1okXRdTOUajtCBF1uG3VuIe33', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_loans`
--

CREATE TABLE `user_loans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 25.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_repayable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_repaid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `purpose` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `term_months` int(11) DEFAULT NULL,
  `status` enum('pending','approved','active','repaid','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notes`
--

CREATE TABLE `user_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `flag` enum('none','important','idea','reminder') NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_projects`
--

CREATE TABLE `user_projects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `project_type` enum('personal','business','community','charity','other') NOT NULL DEFAULT 'personal',
  `goal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `raised_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expected_roi` decimal(5,2) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('planning','active','funded','paused','completed','cancelled') NOT NULL DEFAULT 'planning',
  `visibility` enum('private','members','public') NOT NULL DEFAULT 'private',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_savings`
--

CREATE TABLE `user_savings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(150) NOT NULL,
  `category` enum('savings','investment','asset','liability','other') NOT NULL DEFAULT 'savings',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','matured','closed','paused') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_admin` (`admin_id`),
  ADD KEY `idx_audit_target` (`target_type`,`target_id`);

--
-- Indexes for table `admin_member_notes`
--
ALTER TABLE `admin_member_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_member_notes_member` (`member_id`),
  ADD KEY `idx_admin_member_notes_admin` (`admin_id`);

--
-- Indexes for table `admin_projects`
--
ALTER TABLE `admin_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_projects_admin` (`admin_id`),
  ADD KEY `idx_admin_projects_status` (`status`);

--
-- Indexes for table `admin_savings`
--
ALTER TABLE `admin_savings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_savings_admin` (`admin_id`),
  ADD KEY `idx_admin_savings_cat` (`category`),
  ADD KEY `idx_admin_savings_status` (`status`);

--
-- Indexes for table `approved_loans`
--
ALTER TABLE `approved_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_user` (`user_id`),
  ADD KEY `idx_feedback_status` (`status`),
  ADD KEY `idx_feedback_category` (`category`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_investments_user` (`user_id`),
  ADD KEY `idx_investments_status` (`status`);

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `personal_investments`
--
ALTER TABLE `personal_investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_investments_user` (`user_id`);

--
-- Indexes for table `personal_loans`
--
ALTER TABLE `personal_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_loans_user` (`user_id`);

--
-- Indexes for table `personal_proposals`
--
ALTER TABLE `personal_proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_proposals_user` (`user_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposals_user` (`user_id`),
  ADD KEY `idx_proposals_status` (`status`),
  ADD KEY `idx_proposals_type` (`type`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `firebase_uid` (`firebase_uid`);

--
-- Indexes for table `user_loans`
--
ALTER TABLE `user_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_loans_user` (`user_id`),
  ADD KEY `idx_user_loans_status` (`status`);

--
-- Indexes for table `user_notes`
--
ALTER TABLE `user_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_notes_user` (`user_id`);

--
-- Indexes for table `user_projects`
--
ALTER TABLE `user_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_projects_user` (`user_id`),
  ADD KEY `idx_user_projects_status` (`status`);

--
-- Indexes for table `user_savings`
--
ALTER TABLE `user_savings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_savings_user` (`user_id`),
  ADD KEY `idx_user_savings_cat` (`category`),
  ADD KEY `idx_user_savings_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `admin_member_notes`
--
ALTER TABLE `admin_member_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_projects`
--
ALTER TABLE `admin_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_savings`
--
ALTER TABLE `admin_savings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approved_loans`
--
ALTER TABLE `approved_loans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `personal_investments`
--
ALTER TABLE `personal_investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `personal_loans`
--
ALTER TABLE `personal_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `personal_proposals`
--
ALTER TABLE `personal_proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `user_loans`
--
ALTER TABLE `user_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_notes`
--
ALTER TABLE `user_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_projects`
--
ALTER TABLE `user_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_savings`
--
ALTER TABLE `user_savings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approved_loans`
--
ALTER TABLE `approved_loans`
  ADD CONSTRAINT `approved_loans_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_loans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD CONSTRAINT `loan_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
