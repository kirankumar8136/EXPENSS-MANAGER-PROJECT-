<?php
/**
 * SmartSpend Demo Data Seeder
 * Populates a realistic demo profile for evaluation and testing.
 * Can be executed via CLI or directly in the browser.
 *
 * Demo Credentials:
 *   Email: demo@smartspend.io
 *   Password: password123
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');

function output(string $msg, bool $isCli): void
{
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo "<div style='font-family: monospace; padding: 4px 0; color: #10b981;'>" . htmlspecialchars($msg) . "</div>";
    }
}

try {
    $pdo = getDbConnection();
    output("Connecting to SmartSpend Database...", $isCli);

    $pdo->beginTransaction();

    // 1. Remove existing demo user if present to ensure clean idempotent run
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = "demo@smartspend.io" LIMIT 1');
    $stmt->execute();
    $existing = $stmt->fetch();
    if ($existing) {
        $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $del->execute(['id' => $existing['id']]);
        output("Removed previous demo user data for clean seeding.", $isCli);
    }

    // 2. Insert Demo User
    $passHash = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, email, password_hash, currency, currency_code, onboarding_completed, created_at)
        VALUES ("Demo Professional", "demo@smartspend.io", :hash, "₹", "INR", 1, NOW())
    ');
    $stmt->execute(['hash' => $passHash]);
    $userId = (int) $pdo->lastInsertId();
    output("Created Demo User (ID: {$userId}, demo@smartspend.io / password123).", $isCli);

    // 3. User Settings & Opening Balance
    $stmt = $pdo->prepare('
        INSERT INTO user_settings (user_id, monthly_income, income_source_type, income_frequency, salary_day, is_income_fixed, monthly_savings_target, savings_style, budget_period_type, reminder_days_before)
        VALUES (:uid, 65000.00, "salaried", "monthly", 1, 1, 15000.00, "balanced", "calendar_month", "7,3,1,0")
    ');
    $stmt->execute(['uid' => $userId]);

    $stmt = $pdo->prepare('
        INSERT INTO opening_balances (user_id, amount, as_of_date)
        VALUES (:uid, 38500.00, :dt)
    ');
    $stmt->execute(['uid' => $userId, 'dt' => date('Y-m-01', strtotime('-3 months'))]);
    output("Configured salary profile (₹65,000/mo) and opening balance (₹38,500).", $isCli);

    // 4. Fetch Category IDs
    $stmt = $pdo->query('SELECT id, name, type FROM categories WHERE user_id IS NULL');
    $catMap = [];
    while ($row = $stmt->fetch()) {
        $catMap[$row['name']] = (int) $row['id'];
    }

    // 5. Category Budgets
    $budgets = [
        'Housing & Rent'          => 18000.00,
        'Food & Groceries'        => 12000.00,
        'Electricity & Utilities' => 3500.00,
        'Transportation'          => 4500.00,
        'Entertainment & Dining'  => 5500.00,
        'Shopping & Lifestyle'    => 4000.00,
        'Subscriptions & Media'   => 1500.00,
        'Healthcare & Medical'    => 2000.00,
    ];

    $bStmt = $pdo->prepare('INSERT INTO budgets (user_id, category_id, monthly_amount) VALUES (:uid, :cid, :amt)');
    foreach ($budgets as $catName => $amount) {
        if (isset($catMap[$catName])) {
            $bStmt->execute(['uid' => $userId, 'cid' => $catMap[$catName], 'amt' => $amount]);
        }
    }
    output("Configured monthly category budgets.", $isCli);

    // 6. Recurring Bills
    $today = new DateTime('today');
    $bills = [
        [
            'name'       => 'House Rent (Apartment)',
            'cat'        => 'Housing & Rent',
            'amount'     => 18000.00,
            'frequency'  => 'monthly',
            'due_day'    => 1,
            'next_due'   => (new DateTime('first day of next month'))->format('Y-m-01'),
            'notes'      => 'Monthly flat rent transfer to landlord',
        ],
        [
            'name'       => 'Electricity Bill (State Discom)',
            'cat'        => 'Electricity & Utilities',
            'amount'     => 2150.00,
            'frequency'  => 'monthly',
            'due_day'    => (int) (clone $today)->modify('+3 days')->format('j'),
            'next_due'   => (clone $today)->modify('+3 days')->format('Y-m-d'),
            'notes'      => 'CA No. 100293848',
        ],
        [
            'name'       => 'Broadband Fiber (Airtel Xstream)',
            'cat'        => 'Electricity & Utilities',
            'amount'     => 1179.00,
            'frequency'  => 'monthly',
            'due_day'    => (int) (clone $today)->modify('+5 days')->format('j'),
            'next_due'   => (clone $today)->modify('+5 days')->format('Y-m-d'),
            'notes'      => '300 Mbps unlimited plan',
        ],
        [
            'name'       => 'Netflix Premium (4K Screens)',
            'cat'        => 'Subscriptions & Media',
            'amount'     => 649.00,
            'frequency'  => 'monthly',
            'due_day'    => (int) (clone $today)->modify('+8 days')->format('j'),
            'next_due'   => (clone $today)->modify('+8 days')->format('Y-m-d'),
            'notes'      => 'Auto-debit on credit card',
        ],
        [
            'name'       => 'Spotify Family',
            'cat'        => 'Subscriptions & Media',
            'amount'     => 179.00,
            'frequency'  => 'monthly',
            'due_day'    => (int) (clone $today)->modify('+14 days')->format('j'),
            'next_due'   => (clone $today)->modify('+14 days')->format('Y-m-d'),
            'notes'      => 'Monthly music subscription',
        ],
        [
            'name'       => 'Health Insurance Premium (Star Health)',
            'cat'        => 'Insurance',
            'amount'     => 14500.00,
            'frequency'  => 'yearly',
            'due_day'    => 15,
            'next_due'   => (clone $today)->modify('+45 days')->format('Y-m-d'),
            'notes'      => 'Annual floater medical insurance cover',
        ],
    ];

    $billStmt = $pdo->prepare('
        INSERT INTO recurring_bills (user_id, category_id, name, amount, frequency, due_day, next_due_date, start_date, notes, status)
        VALUES (:uid, :cid, :name, :amt, :freq, :dday, :nextdue, :start, :notes, "active")
    ');

    foreach ($bills as $b) {
        $cid = $catMap[$b['cat']] ?? reset($catMap);
        $billStmt->execute([
            'uid'     => $userId,
            'cid'     => $cid,
            'name'    => $b['name'],
            'amt'     => $b['amount'],
            'freq'    => $b['frequency'],
            'dday'    => $b['due_day'],
            'nextdue' => $b['next_due'],
            'start'   => date('Y-m-01', strtotime('-3 months')),
            'notes'   => $b['notes'],
        ]);
    }
    output("Seeded recurring bills (Rent, Electricity, Broadband, Netflix, Insurance).", $isCli);

    // 7. Savings Goals & Contributions
    $goals = [
        [
            'name'     => 'Emergency Safety Fund (6 Months)',
            'target'   => 180000.00,
            'current'  => 95000.00,
            'date'     => date('Y-m-d', strtotime('+8 months')),
            'priority' => 'high',
            'category' => 'Emergency Fund',
        ],
        [
            'name'     => 'Apple MacBook Pro M3',
            'target'   => 145000.00,
            'current'  => 65000.00,
            'date'     => date('Y-m-d', strtotime('+5 months')),
            'priority' => 'medium',
            'category' => 'Technology & Gadgets',
        ],
        [
            'name'     => 'Goa New Year Vacation',
            'target'   => 35000.00,
            'current'  => 28000.00,
            'date'     => date('Y-12-28'),
            'priority' => 'medium',
            'category' => 'Travel & Vacation',
        ],
    ];

    $gStmt = $pdo->prepare('
        INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, monthly_contribution_target, priority, category, status)
        VALUES (:uid, :name, :tgt, :cur, :tdate, :contrib, :prio, :cat, "in_progress")
    ');
    $cStmt = $pdo->prepare('
        INSERT INTO savings_contributions (goal_id, user_id, type, amount, date, notes)
        VALUES (:gid, :uid, "deposit", :amt, :dt, :notes)
    ');

    foreach ($goals as $g) {
        $gStmt->execute([
            'uid'     => $userId,
            'name'    => $g['name'],
            'tgt'     => $g['target'],
            'cur'     => $g['current'],
            'tdate'   => $g['date'],
            'contrib' => round($g['target'] / 10, 2),
            'prio'    => $g['priority'],
            'cat'     => $g['category'],
        ]);
        $gid = (int) $pdo->lastInsertId();

        // Add 2 deposit milestones
        $cStmt->execute([
            'gid'   => $gid,
            'uid'   => $userId,
            'amt'   => round($g['current'] * 0.6, 2),
            'dt'    => date('Y-m-d', strtotime('-40 days')),
            'notes' => 'Monthly automated savings deposit',
        ]);
        $cStmt->execute([
            'gid'   => $gid,
            'uid'   => $userId,
            'amt'   => round($g['current'] * 0.4, 2),
            'dt'    => date('Y-m-d', strtotime('-10 days')),
            'notes' => 'Bonus allocation deposit',
        ]);
    }
    output("Seeded 3 savings goals with contribution history.", $isCli);

    // 8. Generate Realistic Transactions (Historical + Current Month)
    $tStmt = $pdo->prepare('
        INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date, payment_method, notes)
        VALUES (:uid, :cid, :type, :amt, :desc, :dt, :method, :notes)
    ');

    // Salary deposits over past 4 months
    for ($m = 3; $m >= 0; $m--) {
        $salaryDate = date('Y-m-01', strtotime("-$m months"));
        $tStmt->execute([
            'uid'    => $userId,
            'cid'    => $catMap['Salary / Monthly Wage'],
            'type'   => 'income',
            'amt'    => 65000.00,
            'desc'   => 'Monthly Salary Deposit - Tech Solutions Pvt Ltd',
            'dt'     => $salaryDate,
            'method' => 'bank_transfer',
            'notes'  => 'Net pay after PF & TDS',
        ]);
    }

    // Occasional freelance income
    $tStmt->execute([
        'uid'    => $userId,
        'cid'    => $catMap['Freelancing & Consulting'],
        'type'   => 'income',
        'amt'    => 18500.00,
        'desc'   => 'UI/UX Design Consulting Client Milestone',
        'dt'     => date('Y-m-12', strtotime('-1 month')),
        'method' => 'upi',
        'notes'  => 'Freelance side project',
    ]);

    // Current & Recent Month Expenses
    $sampleExpenses = [
        ['Housing & Rent', 18000.00, 'Apartment Monthly Rent Transfer', '02', 'bank_transfer'],
        ['Food & Groceries', 3240.00, 'Nature\'s Basket - Monthly Groceries', '04', 'credit_card'],
        ['Food & Groceries', 850.00, 'Zepto Instant Grocery Delivery', '07', 'upi'],
        ['Food & Groceries', 1420.00, 'Blinkit Weekend Essentials', '12', 'upi'],
        ['Food & Groceries', 2100.00, 'Supermarket Gourmet Staples', '16', 'credit_card'],
        ['Entertainment & Dining', 1450.00, 'Dinner with Friends at Olive Bistro', '05', 'credit_card'],
        ['Entertainment & Dining', 420.00, 'Starbucks Coffee & Snacks', '08', 'upi'],
        ['Entertainment & Dining', 980.00, 'PVR Cinemas Movie Tickets (IMAX)', '14', 'upi'],
        ['Entertainment & Dining', 1250.00, 'Weekend Craft Brewery', '19', 'credit_card'],
        ['Transportation', 2000.00, 'Fuel Refill - Indian Oil Petrol Pump', '03', 'credit_card'],
        ['Transportation', 450.00, 'Uber Ride to Tech Park Meeting', '09', 'upi'],
        ['Transportation', 380.00, 'Uber Premier Ride Home', '15', 'upi'],
        ['Shopping & Lifestyle', 2499.00, 'Uniqlo Casual Shirt & Chinos', '06', 'credit_card'],
        ['Shopping & Lifestyle', 1199.00, 'Amazon Basics Cable & Desk Mat', '11', 'upi'],
        ['Electricity & Utilities', 2150.00, 'Electricity Bill Paid', '02', 'upi'],
        ['Subscriptions & Media', 649.00, 'Netflix Monthly Subscription', '08', 'credit_card'],
        ['Healthcare & Medical', 680.00, 'Apollo Pharmacy Health Supplements', '13', 'upi'],
    ];

    // Seed for current month
    $curYm = date('Y-m');
    foreach ($sampleExpenses as $e) {
        $day = min((int)date('j'), (int)$e[3]);
        $dt = sprintf('%s-%02d', $curYm, $day);
        $cid = $catMap[$e[0]] ?? reset($catMap);
        $tStmt->execute([
            'uid'    => $userId,
            'cid'    => $cid,
            'type'   => 'expense',
            'amt'    => $e[1],
            'desc'   => $e[2],
            'dt'     => $dt,
            'method' => $e[4],
            'notes'  => 'Verified expense',
        ]);
    }

    // Seed previous month expenses for trend & comparison
    $prevYm = date('Y-m', strtotime('-1 month'));
    foreach ($sampleExpenses as $e) {
        $dt = sprintf('%s-%02d', $prevYm, (int)$e[3]);
        $cid = $catMap[$e[0]] ?? reset($catMap);
        // Vary amount slightly
        $amt = round($e[1] * (0.9 + (crc32($e[2]) % 25) / 100), 2);
        $tStmt->execute([
            'uid'    => $userId,
            'cid'    => $cid,
            'type'   => 'expense',
            'amt'    => $amt,
            'desc'   => $e[2],
            'dt'     => $dt,
            'method' => $e[4],
            'notes'  => 'Prior month entry',
        ]);
    }
    output("Seeded multi-month transaction ledger entries.", $isCli);

    // 9. Initial Notifications
    $notifStmt = $pdo->prepare('
        INSERT INTO notifications (user_id, title, message, type, is_read, unique_identifier)
        VALUES (:uid, :title, :msg, :type, 0, :ukey)
    ');
    $notifStmt->execute([
        'uid'   => $userId,
        'title' => 'Upcoming Bill: Electricity Bill',
        'msg'   => 'Your electricity payment of ₹2,150.00 is due in 3 days. Ensure sufficient balance in your bank account.',
        'type'  => 'bill_due',
        'ukey'  => 'demo_notif_bill_1',
    ]);
    $notifStmt->execute([
        'uid'   => $userId,
        'title' => 'Budget Notice: Entertainment & Dining',
        'msg'   => 'You have utilized 74% of your Entertainment budget for this month. ₹1,400 remaining.',
        'type'  => 'budget_warning',
        'ukey'  => 'demo_notif_budget_1',
    ]);
    output("Seeded initial notifications.", $isCli);

    $pdo->commit();

    output("----------------------------------------------------------------", $isCli);
    output("SUCCESS! SmartSpend Demo Environment Initialized Successfully.", $isCli);
    output("Credentials: demo@smartspend.io / password123", $isCli);

    if (!$isCli) {
        echo "<div style='margin-top: 20px;'><a href='login.php' style='padding: 10px 20px; background: #6366f1; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700;'>Proceed to Login &rarr;</a></div>";
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    output("ERROR Seeding Data: " . $e->getMessage(), $isCli);
    if ($isCli) {
        exit(1);
    }
}
