-- Migration to add Admin functionality to NeighbourNet
-- Run this SQL in phpMyAdmin or MySQL command line

USE `net`;

-- Add role column to users table
ALTER TABLE `users` 
ADD COLUMN `role` ENUM('user', 'admin') DEFAULT 'user' AFTER `password`;

-- Create admin_users table for additional admin-specific data
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_level` ENUM('super_admin', 'moderator', 'manager') DEFAULT 'moderator',
  `permissions` TEXT DEFAULT NULL COMMENT 'JSON array of permissions',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default super admin
-- Password: admin123 (hashed with bcrypt)
INSERT INTO `users` 
  (`full_name`, `mobile_number`, `email`, `flat_number`, `password`, `role`) 
VALUES 
  ('Admin User', '9999999999', 'admin@neighbournet.com', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Get the admin user ID and create admin profile
SET @admin_user_id = LAST_INSERT_ID();

INSERT INTO `admin_users` 
  (`user_id`, `admin_level`, `permissions`) 
VALUES 
  (@admin_user_id, 'super_admin', '["all"]');

-- Create admin_activity_logs table
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'user, complaint, announcement, etc',
  `target_id` int(11) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add admin approval fields to existing tables
ALTER TABLE `announcements` 
ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `message`,
ADD COLUMN `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER `created_by`;

ALTER TABLE `events` 
ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `location`,
ADD COLUMN `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER `created_by`;

-- Update users table to add status field
ALTER TABLE `users` 
ADD COLUMN `status` ENUM('active', 'blocked', 'pending') DEFAULT 'active' AFTER `role`;

-- Success message
SELECT 'Admin migration completed successfully!' as message;
