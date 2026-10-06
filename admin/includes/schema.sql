CREATE DATABASE IF NOT EXISTS kingdom_ministry;
USE kingdom_ministry;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `image` VARCHAR(255) DEFAULT NULL,
  `body` LONGTEXT NOT NULL,
  `category_id` INT DEFAULT NULL,
  `status` ENUM('Draft','Published') DEFAULT 'Published',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default admin user: username=admin, password=password
-- password has been hashed using bcrypt in PHP: password_hash('password', PASSWORD_DEFAULT);
INSERT IGNORE INTO `admins` (`username`, `password`) VALUES ('admin', '$2y$10$w6z9T5bHk7TqM9U1y3A8uOa4jLzU7h3LzU7h3LzU7h3LzU7h3LzU'); 
-- NOTE: Please replace password in your live environment. This is just a placeholder.
-- Actual hash for 'password' is:
-- $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
UPDATE `admins` SET `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE `username` = 'admin';
