-- =====================================================================
-- SmartSpend Standalone Demo Seed SQL
-- Imports a complete working demo account: demo@smartspend.io / password123
-- =====================================================================

USE `smartspend`;

-- 1. Insert Demo User (password is 'password123' bcrypt hash)
DELETE FROM `users` WHERE `email` = 'demo@smartspend.io';

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `currency`, `currency_code`, `onboarding_completed`, `created_at`)
VALUES (999, 'Demo Professional', 'demo@smartspend.io', '$2y$10$wK1W0i9bX70u1csqtP9Fhe62yF2kYc7rO9p6Z2P4r0bA50hQ3w6bS', '₹', 'INR', 1, NOW());

-- 2. User Settings
INSERT INTO `user_settings` (`user_id`, `monthly_income`, `income_source_type`, `income_frequency`, `salary_day`, `is_income_fixed`, `monthly_savings_target`, `savings_style`, `budget_period_type`, `reminder_days_before`)
VALUES (999, 65000.00, 'salaried', 'monthly', 1, 1, 15000.00, 'balanced', 'calendar_month', '7,3,1,0')
ON DUPLICATE KEY UPDATE `monthly_income` = 65000.00;

-- 3. Opening Balance
INSERT INTO `opening_balances` (`user_id`, `amount`, `as_of_date`)
VALUES (999, 38500.00, CURDATE())
ON DUPLICATE KEY UPDATE `amount` = 38500.00;

-- 4. Category Budgets
INSERT INTO `budgets` (`user_id`, `category_id`, `monthly_amount`) VALUES
(999, 1, 18000.00), -- Housing & Rent
(999, 2, 12000.00), -- Food & Groceries
(999, 3, 3500.00),  -- Electricity & Utilities
(999, 4, 4500.00),  -- Transportation
(999, 10, 5500.00), -- Entertainment & Dining
(999, 9, 4000.00),  -- Shopping & Lifestyle
(999, 11, 1500.00); -- Subscriptions & Media

-- 5. Recurring Bills
INSERT INTO `recurring_bills` (`user_id`, `category_id`, `name`, `amount`, `is_variable_amount`, `frequency`, `due_day`, `next_due_date`, `start_date`, `notes`, `status`) VALUES
(999, 1, 'House Rent (Apartment)', 18000.00, 0, 'monthly', 1, DATE_ADD(CURDATE(), INTERVAL 15 DAY), CURDATE(), 'Monthly apartment rent', 'active'),
(999, 3, 'Electricity Bill (State Discom)', 2150.00, 1, 'monthly', 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), CURDATE(), 'CA No. 100293848', 'active'),
(999, 3, 'Broadband Fiber (Airtel Xstream)', 1179.00, 0, 'monthly', 8, DATE_ADD(CURDATE(), INTERVAL 6 DAY), CURDATE(), '300 Mbps unlimited plan', 'active'),
(999, 11, 'Netflix Premium', 649.00, 0, 'monthly', 12, DATE_ADD(CURDATE(), INTERVAL 9 DAY), CURDATE(), 'Family subscription', 'active'),
(999, 8, 'Health Insurance Premium', 14500.00, 0, 'yearly', 15, DATE_ADD(CURDATE(), INTERVAL 45 DAY), CURDATE(), 'Annual family health insurance', 'active');

-- 6. Savings Goals
INSERT INTO `savings_goals` (`user_id`, `name`, `target_amount`, `current_amount`, `target_date`, `monthly_contribution_target`, `priority`, `category`, `status`) VALUES
(999, 'Emergency Safety Fund (6 Months)', 180000.00, 95000.00, DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 15000.00, 'high', 'Emergency Fund', 'in_progress'),
(999, 'Apple MacBook Pro M3', 145000.00, 65000.00, DATE_ADD(CURDATE(), INTERVAL 5 MONTH), 15000.00, 'medium', 'Technology & Gadgets', 'in_progress'),
(999, 'Goa Year-End Vacation', 35000.00, 28000.00, DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 5000.00, 'medium', 'Travel & Vacation', 'in_progress');

-- 7. Transactions
INSERT INTO `transactions` (`user_id`, `category_id`, `type`, `amount`, `description`, `transaction_date`, `payment_method`, `notes`) VALUES
(999, 16, 'income', 65000.00, 'Monthly Salary - Tech Corp Ltd', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'bank_transfer', 'Net monthly take-home salary'),
(999, 17, 'income', 18500.00, 'UI/UX Design Consulting Milestone', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'upi', 'Freelance client retainer'),
(999, 1, 'expense', 18000.00, 'House Rent Transfer', DATE_SUB(CURDATE(), INTERVAL 22 DAY), 'bank_transfer', 'Landlord rent payment'),
(999, 2, 'expense', 3240.00, 'Nature\'s Basket - Monthly Groceries', DATE_SUB(CURDATE(), INTERVAL 18 DAY), 'credit_card', 'Monthly household staples'),
(999, 2, 'expense', 850.00, 'Zepto Instant Grocery Delivery', DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'upi', 'Snacks and milk'),
(999, 10, 'expense', 1450.00, 'Dinner with Friends at Olive Bistro', DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'credit_card', 'Social dining'),
(999, 10, 'expense', 420.00, 'Starbucks Coffee & Sandwich', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'upi', 'Coffee catchup'),
(999, 4, 'expense', 2000.00, 'Fuel Refill - Petrol Pump', DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'credit_card', 'Car fuel tank full'),
(999, 4, 'expense', 450.00, 'Uber Ride to Tech Park', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'upi', 'Office commute'),
(999, 9, 'expense', 2499.00, 'Uniqlo Casual Shirt & Chinos', DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'credit_card', 'Apparel purchase'),
(999, 3, 'expense', 2150.00, 'Electricity Bill Paid', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'upi', 'Previous cycle bill'),
(999, 11, 'expense', 649.00, 'Netflix Subscription', DATE_SUB(CURDATE(), INTERVAL 24 DAY), 'credit_card', 'Streaming service');

-- 8. Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`, `unique_identifier`) VALUES
(999, 'Upcoming Bill: Electricity Bill', 'Your electricity bill of ₹2,150.00 is due in 3 days.', 'bill_due', 0, 'seed_sql_bill_1'),
(999, 'Budget Notice: Entertainment & Dining', 'You have utilized 74% of your monthly Entertainment budget.', 'budget_warning', 0, 'seed_sql_notif_2');
