-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for moto_dashboard
CREATE DATABASE IF NOT EXISTS `moto_dashboard` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `moto_dashboard`;

-- Dumping structure for table moto_dashboard.cameras
CREATE TABLE IF NOT EXISTS `cameras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stream_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'demo',
  `stream_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chế độ mô phỏng',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table moto_dashboard.cameras: ~2 rows (approximately)
INSERT INTO `cameras` (`id`, `name`, `location`, `stream_type`, `stream_url`, `is_enabled`, `status`) VALUES
	(1, 'Camera 01 - Cổng chính (Cổng A)', 'Làn xe 2 bánh Cổng A (Khu hành chính & Giảng đường)', 'demo', '', 1, 'Đang hoạt động (Demo)'),
	(2, 'Camera 02 - Cổng phụ (Cổng B)', 'Làn xe 2 bánh Cổng B (Bãi giữ xe Sinh viên)', 'demo', '', 1, 'Đang hoạt động (Demo)');

-- Dumping structure for table moto_dashboard.detections
CREATE TABLE IF NOT EXISTS `detections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `camera_id` int NOT NULL,
  `track_id` int DEFAULT NULL,
  `license_plate` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chưa đọc được',
  `vehicle_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Xe khách',
  `has_helmet` tinyint(1) NOT NULL DEFAULT '1',
  `confidence` float NOT NULL DEFAULT '0.9',
  `direction` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Vào',
  `bbox_x` float DEFAULT '20',
  `bbox_y` float DEFAULT '25',
  `bbox_w` float DEFAULT '28',
  `bbox_h` float DEFAULT '42',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detection_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_det_time` (`detection_time`),
  KEY `idx_det_cam` (`camera_id`),
  KEY `idx_det_plate` (`license_plate`),
  CONSTRAINT `fk_det_camera` FOREIGN KEY (`camera_id`) REFERENCES `cameras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table moto_dashboard.detections: ~18 rows (approximately)
INSERT INTO `detections` (`id`, `camera_id`, `track_id`, `license_plate`, `vehicle_group`, `has_helmet`, `confidence`, `direction`, `bbox_x`, `bbox_y`, `bbox_w`, `bbox_h`, `image_path`, `is_verified`, `note`, `detection_time`) VALUES
	(1, 1, 101, '65B1-123.45', 'Xe Sinh viên', 1, 0.97, 'Vào', 22, 28, 26, 44, NULL, 1, NULL, '2026-11-19 07:12:15'),
	(2, 1, 102, '65B2-456.78', 'Xe Sinh viên', 1, 0.95, 'Vào', 35, 30, 24, 42, NULL, 1, NULL, '2026-11-19 07:25:40'),
	(3, 1, 103, '64E1-998.21', 'Xe khách', 0, 0.91, 'Vào', 40, 26, 28, 45, NULL, 0, NULL, '2026-11-19 07:41:10'),
	(4, 2, 104, '65C1-889.12', 'Xe Giảng viên', 1, 0.98, 'Vào', 18, 24, 25, 43, NULL, 1, NULL, '2026-11-19 08:05:33'),
	(5, 1, 105, '65D1-202.68', 'Xe Giảng viên', 1, 0.96, 'Vào', 30, 22, 27, 46, NULL, 1, NULL, '2026-11-19 09:18:20'),
	(6, 2, 106, '95B1-552.19', 'Xe khách', 1, 0.89, 'Vào', 45, 28, 26, 40, NULL, 0, NULL, '2026-11-19 10:42:05'),
	(7, 1, 107, '65B1-123.45', 'Xe Sinh viên', 1, 0.97, 'Ra', 25, 30, 26, 44, NULL, 1, NULL, '2026-11-19 11:20:50'),
	(8, 1, 108, '64E1-998.21', 'Xe khách', 0, 0.92, 'Ra', 38, 27, 27, 44, NULL, 0, NULL, '2026-11-19 11:38:12'),
	(9, 1, 109, '65F1-334.56', 'Xe Sinh viên', 1, 0.94, 'Vào', 28, 25, 26, 42, NULL, 0, NULL, '2026-11-19 13:15:09'),
	(10, 2, 110, '83P2-771.04', 'Xe khách', 0, 0.88, 'Vào', 32, 29, 25, 41, NULL, 0, NULL, '2026-11-19 13:48:27'),
	(11, 1, 581, '65B1-123.45', 'Xe Sinh viên', 1, 0.98, 'Vào', 45, 23, 30, 48, NULL, 0, NULL, '2026-11-19 23:19:46'),
	(12, 2, 995, '65B2-456.78', 'Xe Sinh viên', 0, 0.89, 'Vào', 45, 28, 25, 46, NULL, 0, NULL, '2026-11-19 23:20:05'),
	(13, 1, 709, '65B2-456.78', 'Xe Sinh viên', 1, 0.99, 'Ra', 48, 32, 23, 43, NULL, 0, NULL, '2026-11-19 23:20:08'),
	(14, 2, 638, '65C1-889.12', 'Xe Giảng viên', 0, 0.97, 'Ra', 52, 24, 25, 41, NULL, 0, NULL, '2026-11-19 23:36:43'),
	(15, 1, 411, '65D1-202.68', 'Xe Giảng viên', 1, 0.93, 'Vào', 24, 31, 29, 47, NULL, 0, NULL, '2026-11-20 00:00:09'),
	(16, 1, 471, '65B2-456.78', 'Xe Sinh viên', 1, 0.98, 'Ra', 42, 29, 30, 43, NULL, 0, NULL, '2026-11-20 00:00:16'),
	(17, 1, 703, '65C1-889.12', 'Xe Giảng viên', 1, 0.92, 'Vào', 37, 33, 28, 48, NULL, 0, NULL, '2026-11-20 00:00:22'),
	(18, 2, 231, '64E1-902.11', 'Xe khách', 0, 0.88, 'Vào', 45, 36, 26, 48, NULL, 0, NULL, '2026-11-20 00:06:24');

-- Dumping structure for table moto_dashboard.registered_vehicles
CREATE TABLE IF NOT EXISTS `registered_vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `license_plate` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sinh viên',
  `vehicle_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_plate` (`license_plate`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table moto_dashboard.registered_vehicles: ~5 rows (approximately)
INSERT INTO `registered_vehicles` (`id`, `license_plate`, `owner_name`, `owner_role`, `vehicle_model`, `is_active`, `created_at`) VALUES
	(1, '65B1-123.45', 'Đoàn Thanh Tân', 'Sinh viên', 'Honda Wave Alpha', 1, '2026-11-19 23:16:16'),
	(2, '65B2-456.78', 'Ngô Minh Phúc', 'Sinh viên', 'Yamaha Exciter 155', 1, '2026-11-19 23:16:16'),
	(3, '65C1-889.12', 'Nguyễn Văn An', 'Giảng viên', 'Honda Air Blade', 1, '2026-11-19 23:16:16'),
	(4, '65D1-202.68', 'Trần Thị Bích', 'Giảng viên', 'Honda Vision', 1, '2026-11-19 23:16:16'),
	(5, '65F1-334.56', 'Lê Hoàng Nam', 'Sinh viên', 'Honda Winner X', 1, '2026-11-19 23:16:16');

-- Dumping structure for table moto_dashboard.system_settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `system_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MotoGuard AI - Giám sát Xe gắn máy',
  `school_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Trường ĐH Kỹ thuật - Công nghệ Cần Thơ',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '256 Nguyễn Văn Cừ, Ninh Kiều, Cần Thơ',
  `yolo_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'YOLOv8n-Moto-Helmet-OCR',
  `yolo_confidence` float NOT NULL DEFAULT '0.5',
  `ocr_threshold` float NOT NULL DEFAULT '0.8',
  `helmet_alert_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `guest_alert_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `auto_refresh` tinyint(1) NOT NULL DEFAULT '1',
  `refresh_interval` int NOT NULL DEFAULT '5',
  `sound_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `api_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MOTO_AI_KEY_2026',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table moto_dashboard.system_settings: ~0 rows (approximately)
INSERT INTO `system_settings` (`id`, `system_name`, `school_name`, `address`, `yolo_model`, `yolo_confidence`, `ocr_threshold`, `helmet_alert_enabled`, `guest_alert_enabled`, `auto_refresh`, `refresh_interval`, `sound_enabled`, `api_key`) VALUES
	(1, 'MotoGuard AI - Giám sát Xe gắn máy', 'Trường ĐH Kỹ thuật - Công nghệ Cần Thơ', '256 Nguyễn Văn Cừ, Ninh Kiều, Cần Thơ', 'YOLOv8n-Moto-Helmet-OCR', 0.5, 0.8, 1, 0, 1, 5, 1, 'MOTO_AI_KEY_2026');

-- Dumping structure for table moto_dashboard.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nhân viên',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table moto_dashboard.users: ~1 rows (approximately)
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `is_active`, `created_at`) VALUES
	(1, 'admin@gmail.com', '$2y$10$76IEfvP5WTMsXn476KMhgOFvunWHoTDAuSOc4KzHmYjZCiwhgCQai', 'Quản trị viên Hệ thống', 'Admin', 1, '2026-11-19 23:17:39');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
