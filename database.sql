-- ============================================================================
-- Ledger: personal budget and expense tracker
-- Database setup script (MySQL 8+ / MariaDB), amounts in INR
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `ledger_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `ledger_db`;

-- ----------------------------------------------------------------------------
-- Table structure for `categories`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `profile`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(60) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `color` VARCHAR(10) NOT NULL DEFAULT '#64748b',
    `icon` VARCHAR(40) NOT NULL DEFAULT 'folder',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_category_name_type` UNIQUE (`name`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `transactions`
-- ----------------------------------------------------------------------------
CREATE TABLE `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('income', 'expense') NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_category` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    INDEX `idx_trans_date` (`transaction_date`),
    INDEX `idx_trans_type` (`type`),
    INDEX `idx_trans_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `budgets`
-- ----------------------------------------------------------------------------
CREATE TABLE `budgets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `month` TINYINT UNSIGNED NOT NULL,
    `year` SMALLINT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_budgets_category` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    CONSTRAINT `uq_category_month_year` UNIQUE (`category_id`, `month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `profile` (single account holder, id = 1)
-- ----------------------------------------------------------------------------
CREATE TABLE `profile` (
    `id` TINYINT UNSIGNED PRIMARY KEY,
    `full_name` VARCHAR(80) NOT NULL,
    `email` VARCHAR(120) NULL,
    `phone` VARCHAR(20) NULL,
    `occupation` VARCHAR(80) NULL,
    `city` VARCHAR(60) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Default data
-- ============================================================================

INSERT INTO `profile` (`id`, `full_name`) VALUES (1, 'Account Holder');

-- Categories

-- Income Categories
INSERT INTO `categories` (`id`, `name`, `type`, `color`, `icon`) VALUES
(1, 'Salary & Monthly Pay', 'income', '#059669', 'briefcase'),
(2, 'Freelance & Consulting', 'income', '#0d9488', 'laptop'),
(3, 'SIP, Stocks & Mutual Funds', 'income', '#2563eb', 'trending-up'),
(4, 'Business & Rental Income', 'income', '#4f46e5', 'home'),
(5, 'Interest, Cashback & Rewards', 'income', '#7c3aed', 'folder');

-- Expense Categories
INSERT INTO `categories` (`id`, `name`, `type`, `color`, `icon`) VALUES
(6, 'Rent & Maintenance', 'expense', '#475569', 'home'),
(7, 'Groceries & Kirana', 'expense', '#16a34a', 'shopping-cart'),
(8, 'Dining, Swiggy & Zomato', 'expense', '#ea580c', 'coffee'),
(9, 'Electricity, Gas & Water', 'expense', '#0284c7', 'zap'),
(10, 'Fuel, Metro & Commute', 'expense', '#ca8a04', 'truck'),
(11, 'Medical, Clinic & Pharmacy', 'expense', '#dc2626', 'heart'),
(12, 'Subscriptions (OTT, Prime, Wifi)', 'expense', '#6366f1', 'monitor'),
(13, 'Mobile Recharge & Broadband', 'expense', '#0891b2', 'zap'),
(14, 'Education, Books & Fees', 'expense', '#0284c7', 'book-open'),
(15, 'Shopping & Festivals', 'expense', '#9333ea', 'tag'),
(16, 'UPI & Daily Expenses', 'expense', '#d97706', 'folder');
