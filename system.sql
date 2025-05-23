-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2025 at 03:53 AM
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
-- Database: `system`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `other_gender` varchar(50) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `occupation` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `region` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(10) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `purpose` text NOT NULL,
  `profile_photo` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reference_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `last_name`, `first_name`, `middle_name`, `gender`, `other_gender`, `birthdate`, `age`, `occupation`, `address`, `region`, `email`, `contact`, `appointment_date`, `appointment_time`, `purpose`, `profile_photo`, `status`, `created_at`, `updated_at`, `reference_id`) VALUES
(1, 3, 'Tolentino', 'Niephrell', 'fernandez', 'Male', '', '2003-07-20', 21, 'student', 'Near La Finca de Gallo - Negros Province PH • Blk 23 Lot 2, Benares Street, Capitol Heights, Villamonte, Bacolod City, Negros Occidental, 6100, Region Vi: Western Visayas, Philippines', 'Negros Occidental', 'tolentinoniephrell@gmail.com', '9610703277', '2025-05-15', '09:09:00', 'checking', 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNj', 'Approved', '2025-05-01 09:05:46', '2025-05-23 01:49:04', 'NT-20250515');

-- --------------------------------------------------------

--
-- Table structure for table `data`
--

CREATE TABLE `data` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `otp_send_time` datetime DEFAULT NULL,
  `verify_otp` varchar(100) NOT NULL,
  `ip` varchar(100) NOT NULL,
  `status_Account` varchar(100) NOT NULL,
  `user_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Admin, 0 = Client'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `data`
--

INSERT INTO `data` (`user_id`, `email`, `password`, `otp`, `otp_send_time`, `verify_otp`, `ip`, `status_Account`, `user_status`) VALUES
(1, 'nepneptolentino@gmail.com', '$2y$10$PNdh0cRmwzKQ0bdLhIHY1OzqB9fxA2ZSz/2atiLo2/WUHltTWXL.C', NULL, '2025-04-16 06:59:51', '588894', '::1', 'verified', 0),
(3, 'tolentinoniephrell@gmail.com', '$2y$10$PAKoPg448lt0Xkm9mbYwPum6bM1XNYQ7gmNvBfYnIWBlERsopFucW', NULL, '2025-04-24 07:48:41', '419545', '::1', 'verified', 0),
(4, 'emilyolayra@gmail.com', '$2y$10$XkUPU0QuVWm7kC5DqNUGnehSAeP.yf7lkRovwfqeeNKqiWsqv8662', NULL, NULL, '', '::1', 'verified', 1),
(5, 'ismaelsaripada123@gmail.com', '$2y$10$b/zFvR70JKcEHzsKkekO6O91xKvxq7euGPo/yTeTmc.b68.90DlLO', NULL, '2025-05-19 05:11:18', '321005', '::1', 'verified', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_information`
--

CREATE TABLE `user_information` (
  `info_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `gender` varchar(20) NOT NULL,
  `other_gender` varchar(100) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `occupation` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `region` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(15) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_information`
--

INSERT INTO `user_information` (`info_id`, `user_id`, `first_name`, `last_name`, `middle_name`, `gender`, `other_gender`, `birthdate`, `age`, `occupation`, `address`, `region`, `email`, `contact`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 3, 'Niephrell', 'Tolentino', 'fernandez', 'Male', '', '2003-06-22', 21, 'student', 'Near La Finca de Gallo - Negros Province PH • Blk 23 Lot 2, Benares Street, Capitol Heights, Villamonte, Bacolod City, Negros Occidental, 6100, Region Vi: Western Visayas, Philippines', 'Negros Occidental', 'tolentinoniephrell@gmail.com', '9610703277', 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNj', '2025-05-01 17:05:46', '2025-05-01 17:10:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `data`
--
ALTER TABLE `data`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `idx_email` (`email`);

--
-- Indexes for table `user_information`
--
ALTER TABLE `user_information`
  ADD PRIMARY KEY (`info_id`),
  ADD UNIQUE KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `data`
--
ALTER TABLE `data`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_information`
--
ALTER TABLE `user_information`
  MODIFY `info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `data` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_information`
--
ALTER TABLE `user_information`
  ADD CONSTRAINT `user_information_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `data` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
