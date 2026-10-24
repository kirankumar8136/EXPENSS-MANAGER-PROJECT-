-- =====================================================================
-- SmartSpend Database Schema
-- Smart Expense Tracker and Personal Money Management System
-- Compatibility: MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `smartspend` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smartspend`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT '₹',
    `currency_code` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User Settings & Financial Profile
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `monthly_income` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `income_source_type` ENUM('salaried', 'student', 'freelancer', 'self_employed', 'irregular') NOT NULL DEFAULT 'salaried',
    `income_frequency` ENUM('monthly', 'biweekly', 'weekly', 'irregular') NOT NULL DEFAULT 'monthly',
    `salary_day` TINYINT NOT NULL DEFAULT 1,
    `is_income_fixed` TINYINT(1) NOT NULL DEFAULT 1,
    `monthly_savings_target` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `savings_style` ENUM('conservative', 'balanced', 'ambitious') NOT NULL DEFAULT 'balanced',
    `budget_period_type` ENUM('calendar_month', 'salary_cycle') NOT NULL DEFAULT 'calendar_month',
    `reminder_days_before` VARCHAR(50) NOT NULL DEFAULT '7,3,1,0',
    `theme` VARCHAR(20) NOT NULL DEFAULT 'dark',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Opening Balances
CREATE TABLE IF NOT EXISTS `opening_balances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `as_of_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_opening_balances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Categories Table (System defaults and user custom categories)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL, -- NULL indicates global system default
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('expense', 'income') NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'tag',
    `color` VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `is_essential` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_category_user_type` (`user_id`, `type`),
    CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `type` ENUM('expense', 'income') NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'bank_transfer', 'upi', 'credit_card', 'debit_card', 'cheque', 'other') NOT NULL DEFAULT 'upi',
    `notes` TEXT NULL,
    `bill_payment_id` INT NULL,
    `savings_goal_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_txn_user_date` (`user_id`, `transaction_date`),
    INDEX `idx_txn_user_type` (`user_id`, `type`),
    INDEX `idx_txn_category` (`category_id`),
    CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transactions_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Recurring Bills Table
CREATE TABLE IF NOT EXISTS `recurring_bills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `is_variable_amount` TINYINT(1) NOT NULL DEFAULT 0,
    `frequency` ENUM('weekly', 'monthly', 'bi_monthly', 'quarterly', 'half_yearly', 'yearly') NOT NULL DEFAULT 'monthly',
    `due_day` TINYINT NOT NULL DEFAULT 1,
    `next_due_date` DATE NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `reminder_schedule` VARCHAR(50) NOT NULL DEFAULT '7,3,1,0',
    `notes` TEXT NULL,
    `status` ENUM('active', 'paused', 'archived') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_bills_user_date` (`user_id`, `next_due_date`),
    INDEX `idx_bills_user_status` (`user_id`, `status`),
    CONSTRAINT `fk_bills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bills_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bill Payments Table
CREATE TABLE IF NOT EXISTS `bill_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bill_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `due_date` DATE NOT NULL,
    `paid_date` DATE NOT NULL,
    `amount_paid` DECIMAL(12, 2) NOT NULL,
    `payment_method` ENUM('cash', 'bank_transfer', 'upi', 'credit_card', 'debit_card', 'cheque', 'other') NOT NULL DEFAULT 'upi',
    `payment_reference` VARCHAR(100) NULL,
    `transaction_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_bill_occurrence` (`bill_id`, `due_date`),
    INDEX `idx_bill_payments_user` (`user_id`, `paid_date`),
    CONSTRAINT `fk_bill_payments_bill` FOREIGN KEY (`bill_id`) REFERENCES `recurring_bills` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bill_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bill_payments_txn` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Category Budgets Table
CREATE TABLE IF NOT EXISTS `budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `monthly_amount` DECIMAL(12, 2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_category_budget` (`user_id`, `category_id`),
    CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_budgets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Savings Goals Table
CREATE TABLE IF NOT EXISTS `savings_goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `target_amount` DECIMAL(12, 2) NOT NULL,
    `current_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `target_date` DATE NOT NULL,
    `monthly_contribution_target` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `status` ENUM('in_progress', 'completed', 'paused') NOT NULL DEFAULT 'in_progress',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_goals_user_status` (`user_id`, `status`),
    CONSTRAINT `fk_savings_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Savings Contributions History Table
CREATE TABLE IF NOT EXISTS `savings_contributions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `type` ENUM('deposit', 'withdrawal') NOT NULL DEFAULT 'deposit',
    `amount` DECIMAL(12, 2) NOT NULL,
    `date` DATE NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contributions_goal` (`goal_id`),
    INDEX `idx_contributions_user` (`user_id`, `date`),
    CONSTRAINT `fk_savings_contrib_goal` FOREIGN KEY (`goal_id`) REFERENCES `savings_goals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_savings_contrib_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('bill_due', 'bill_overdue', 'budget_warning', 'savings_alert', 'system') NOT NULL DEFAULT 'bill_due',
    `bill_id` INT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `unique_identifier` VARCHAR(100) NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_bill` FOREIGN KEY (`bill_id`) REFERENCES `recurring_bills` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DEFAULT SYSTEM CATEGORIES
-- =====================================================================

-- Default Expense Categories
INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `is_system`, `is_essential`) VALUES
(NULL, 'Housing & Rent', 'expense', 'home', '#EF4444', 1, 1),
(NULL, 'Food & Groceries', 'expense', 'shopping-cart', '#F59E0B', 1, 1),
(NULL, 'Electricity & Utilities', 'expense', 'zap', '#EAB308', 1, 1),
(NULL, 'Transportation', 'expense', 'truck', '#3B82F6', 1, 1),
(NULL, 'Healthcare & Medical', 'expense', 'activity', '#EC4899', 1, 1),
(NULL, 'Education', 'expense', 'book-open', '#8B5CF6', 1, 1),
(NULL, 'Loan & EMI Payments', 'expense', 'credit-card', '#DC2626', 1, 1),
(NULL, 'Insurance', 'expense', 'shield', '#06B6D4', 1, 1),
(NULL, 'Shopping & Lifestyle', 'expense', 'shopping-bag', '#10B981', 1, 0),
(NULL, 'Entertainment & Dining', 'expense', 'film', '#F97316', 1, 0),
(NULL, 'Subscriptions & Media', 'expense', 'tv', '#6366F1', 1, 0),
(NULL, 'Electronics & Gadgets', 'expense', 'cpu', '#14B8A6', 1, 0),
(NULL, 'Travel & Vacations', 'expense', 'compass', '#0EA5E9', 1, 0),
(NULL, 'Personal Care', 'expense', 'smile', '#A855F7', 1, 0),
(NULL, 'Other Expense', 'expense', 'more-horizontal', '#64748B', 1, 0);

-- Default Income Categories
INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `is_system`, `is_essential`) VALUES
(NULL, 'Salary / Monthly Wage', 'income', 'briefcase', '#10B981', 1, 0),
(NULL, 'Freelancing & Consulting', 'income', 'laptop', '#3B82F6', 1, 0),
(NULL, 'Business Revenue', 'income', 'trending-up', '#8B5CF6', 1, 0),
(NULL, 'Investments & Dividends', 'income', 'pie-chart', '#06B6D4', 1, 0),
(NULL, 'Gifts & Grants', 'income', 'gift', '#F59E0B', 1, 0),
(NULL, 'Other Income', 'income', 'plus-circle', '#64748B', 1, 0);
