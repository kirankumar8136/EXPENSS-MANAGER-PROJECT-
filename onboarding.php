<?php
/**
 * SmartSpend 5-Step Financial Onboarding Wizard
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/BudgetPlanner.php';

requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

// Handle Onboarding Completion Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_onboarding') {
    validateCsrfOrDie();

    $incomeSource = $_POST['income_source'] ?? 'salaried';
    $monthlyIncome = (float) ($_POST['monthly_income'] ?? 0.0);
    $incomeFrequency = $_POST['income_frequency'] ?? 'monthly';
    $salaryDay = max(1, min(31, (int) ($_POST['salary_day'] ?? 1)));
    $isIncomeFixed = (int) ($_POST['is_income_fixed'] ?? 1);
    $savingsStyle = $_POST['savings_style'] ?? 'balanced';
    $savingsTarget = (float) ($_POST['savings_target'] ?? 0.0);
    $openingBalance = (float) ($_POST['opening_balance'] ?? 0.0);

    // Commitments
    $rentAmount = (float) ($_POST['commit_rent'] ?? 0.0);
    $groceriesAmount = (float) ($_POST['commit_groceries'] ?? 0.0);
    $utilitiesAmount = (float) ($_POST['commit_utilities'] ?? 0.0);
    $internetAmount = (float) ($_POST['commit_internet'] ?? 0.0);
    $transportAmount = (float) ($_POST['commit_transport'] ?? 0.0);
    $loanAmount = (float) ($_POST['commit_loan'] ?? 0.0);

    // Goal
    $goalName = trim($_POST['goal_name'] ?? 'Emergency Fund');
    $goalTarget = (float) ($_POST['goal_target'] ?? ($monthlyIncome * 3));
    $goalDate = $_POST['goal_date'] ?? date('Y-m-d', strtotime('+12 months'));

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // 1. Update user_settings
        $stmt = $pdo->prepare('
            UPDATE user_settings SET
                monthly_income = :income,
                income_source_type = :stype,
                income_frequency = :freq,
                salary_day = :sday,
                is_income_fixed = :fixed,
                monthly_savings_target = :starget,
                savings_style = :sstyle,
                updated_at = NOW()
            WHERE user_id = :uid
        ');
        $stmt->execute([
            'income'  => $monthlyIncome,
            'stype'   => $incomeSource,
            'freq'    => $incomeFrequency,
            'sday'    => $salaryDay,
            'fixed'   => $isIncomeFixed,
            'starget' => $savingsTarget,
            'sstyle'  => $savingsStyle,
            'uid'     => $userId,
        ]);

        // 2. Set opening balance
        $stmt = $pdo->prepare('
            INSERT INTO opening_balances (user_id, amount, as_of_date)
            VALUES (:uid, :amt, :today)
            ON DUPLICATE KEY UPDATE amount = :amt2, as_of_date = :today2
        ');
        $stmt->execute([
            'uid'    => $userId,
            'amt'    => $openingBalance,
            'today'  => date('Y-m-d'),
            'amt2'   => $openingBalance,
            'today2' => date('Y-m-d'),
        ]);

        // 3. Fetch Category IDs for setting initial budgets & recurring bills
        $stmt = $pdo->query('SELECT id, name FROM categories WHERE user_id IS NULL AND type = "expense"');
        $catMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['Housing & Rent' => 1, ...]
        $catFlip = array_flip($catMap);

        // Helper to insert budget
        $insertBudget = $pdo->prepare('
            INSERT INTO budgets (user_id, category_id, monthly_amount)
            VALUES (:uid, :cid, :amt)
            ON DUPLICATE KEY UPDATE monthly_amount = :amt2
        ');

        if ($rentAmount > 0 && isset($catFlip['Housing & Rent'])) {
            $insertBudget->execute(['uid' => $userId, 'cid' => $catFlip['Housing & Rent'], 'amt' => $rentAmount, 'amt2' => $rentAmount]);
            // Also create a recurring bill for rent
            $billStmt = $pdo->prepare('
                INSERT INTO recurring_bills (user_id, category_id, name, amount, frequency, due_day, next_due_date, start_date, notes)
                VALUES (:uid, :cid, "House Rent", :amt, "monthly", :sday, :nextdue, :start, "Monthly residence rent")
            ');
            $nextDue = date('Y-m-') . sprintf('%02d', min($salaryDay, 28));
            if ($nextDue < date('Y-m-d')) {
                $nextDue = date('Y-m-d', strtotime('+1 month', strtotime($nextDue)));
            }
            $billStmt->execute([
                'uid'     => $userId,
                'cid'     => $catFlip['Housing & Rent'],
                'amt'     => $rentAmount,
                'sday'    => min($salaryDay, 28),
                'nextdue' => $nextDue,
                'start'   => date('Y-m-01'),
            ]);
        }

        if ($groceriesAmount > 0 && isset($catFlip['Food & Groceries'])) {
            $insertBudget->execute(['uid' => $userId, 'cid' => $catFlip['Food & Groceries'], 'amt' => $groceriesAmount, 'amt2' => $groceriesAmount]);
        }
        if ($utilitiesAmount > 0 && isset($catFlip['Electricity & Utilities'])) {
            $insertBudget->execute(['uid' => $userId, 'cid' => $catFlip['Electricity & Utilities'], 'amt' => $utilitiesAmount, 'amt2' => $utilitiesAmount]);
        }
        if ($transportAmount > 0 && isset($catFlip['Transportation'])) {
            $insertBudget->execute(['uid' => $userId, 'cid' => $catFlip['Transportation'], 'amt' => $transportAmount, 'amt2' => $transportAmount]);
        }
        if ($loanAmount > 0 && isset($catFlip['Loan & EMI Payments'])) {
            $insertBudget->execute(['uid' => $userId, 'cid' => $catFlip['Loan & EMI Payments'], 'amt' => $loanAmount, 'amt2' => $loanAmount]);
        }

        // 4. Create primary savings goal
        if ($goalTarget > 0) {
            $stmt = $pdo->prepare('
                INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, monthly_contribution_target, priority, status)
                VALUES (:uid, :name, :tgt, 0.00, :tdate, :contrib, "high", "in_progress")
            ');
            $stmt->execute([
                'uid'     => $userId,
                'name'    => $goalName,
                'tgt'     => $goalTarget,
                'tdate'   => $goalDate,
                'contrib' => $savingsTarget,
            ]);
        }

        // 5. Mark onboarding completed
        $stmt = $pdo->prepare('UPDATE users SET onboarding_completed = 1 WHERE id = :uid');
        $stmt->execute(['uid' => $userId]);

        $pdo->commit();

        $_SESSION['onboarding_completed'] = true;
        flash('success', 'Financial profile created successfully! Welcome to your personalized SmartSpend dashboard.');
        redirect('dashboard.php');
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Onboarding error: ' . $e->getMessage());
        flash('danger', 'Error creating financial plan: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Onboarding — SmartSpend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .onboarding-container {
            max-width: 720px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }
        .step-progress {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            position: relative;
        }
        .step-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
            transform: translateY(-50%);
        }
        .step-pill {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-pill);
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-muted);
            z-index: 2;
            position: relative;
            transition: var(--transition);
        }
        .step-pill.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 12px rgba(99, 102, 241, 0.4);
        }
        .step-pill.done {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        .wizard-step {
            display: none;
            animation: fadeIn 0.25s ease;
        }
        .wizard-step.active {
            display: block;
        }
        .option-card {
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
        }
        .option-card:hover {
            border-color: var(--primary);
            background: var(--bg-card-hover);
        }
        .option-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }
    </style>
</head>
<body>
<div class="onboarding-container">
    <!-- Header -->
    <div style="text-align: center; margin-bottom: 32px;">
        <div class="brand-icon" style="margin: 0 auto 12px; width: 44px; height: 44px;">
            <i data-lucide="wallet-cards" style="width: 24px; height: 24px;"></i>
        </div>
        <h2>Personalized Financial Onboarding</h2>
        <p style="font-size: 0.88rem;">Let's build a smart budget tailored to your real income and monthly commitments.</p>
    </div>

    <!-- Stepper Indicator -->
    <div class="step-progress">
        <div class="step-pill active" id="pill-1">1</div>
        <div class="step-pill" id="pill-2">2</div>
        <div class="step-pill" id="pill-3">3</div>
        <div class="step-pill" id="pill-4">4</div>
        <div class="step-pill" id="pill-5">5</div>
    </div>

    <!-- Form -->
    <form action="onboarding.php" method="POST" id="onboardingForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="complete_onboarding">

        <!-- STEP 1: Personal Profile & Current Balance -->
        <div class="card wizard-step active" id="step-1">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i data-lucide="user" style="color: var(--primary);"></i> Step 1: Personal & Account Profile</h3>
                    <div class="card-subtitle">Confirm your account details and current bank/cash reserve.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= sanitize($user['full_name'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" value="<?= sanitize($user['email'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label">Current Bank & Cash Balance (Opening Reserve)</label>
                <input type="number" step="0.01" min="0" name="opening_balance" id="opening_balance" class="form-control" placeholder="e.g. 25000.00" value="0.00">
                <small class="form-text">Your starting balance helps SmartSpend accurately compute available cash after upcoming bills.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next: Income Setup &rarr;</button>
            </div>
        </div>

        <!-- STEP 2: Income Information -->
        <div class="card wizard-step" id="step-2">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i data-lucide="briefcase" style="color: var(--success);"></i> Step 2: Income Information</h3>
                    <div class="card-subtitle">Tell us about how and when you earn.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Primary Source of Income</label>
                <select name="income_source" id="income_source" class="form-select">
                    <option value="salaried" selected>Salaried Employee (Regular Monthly Pay)</option>
                    <option value="freelancer">Freelancer / Consultant (Contract Basis)</option>
                    <option value="self_employed">Business Owner / Self-Employed</option>
                    <option value="student">Student / Allowance</option>
                    <option value="irregular">Irregular / Commission Based</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Monthly Take-Home Income (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="monthly_income" id="monthly_income" class="form-control" placeholder="e.g. 45000.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Income Frequency</label>
                    <select name="income_frequency" class="form-select">
                        <option value="monthly" selected>Monthly</option>
                        <option value="biweekly">Every 2 Weeks</option>
                        <option value="weekly">Weekly</option>
                        <option value="irregular">Irregular</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Salary / Pay Day of Month</label>
                    <input type="number" min="1" max="31" name="salary_day" id="salary_day" class="form-control" value="1" required>
                    <small class="form-text">Day 1 to 31 when your salary is credited.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Income Nature</label>
                    <select name="is_income_fixed" class="form-select">
                        <option value="1" selected>Fixed & Predictable</option>
                        <option value="0">Variable / Fluctuates</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="goToStep(1)">&larr; Back</button>
                <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next: Monthly Commitments &rarr;</button>
            </div>
        </div>

        <!-- STEP 3: Monthly Commitments -->
        <div class="card wizard-step" id="step-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i data-lucide="receipt" style="color: var(--danger);"></i> Step 3: Regular Monthly Commitments</h3>
                    <div class="card-subtitle">Enter rough estimates for your recurring essential bills. (You can skip or adjust later).</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Rent / Housing (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_rent" id="commit_rent" class="form-control" placeholder="e.g. 12000.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Food & Groceries (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_groceries" id="commit_groceries" class="form-control" placeholder="e.g. 7000.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Electricity & Utilities (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_utilities" id="commit_utilities" class="form-control" placeholder="e.g. 1800.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Internet & Mobile (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_internet" id="commit_internet" class="form-control" placeholder="e.g. 1000.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Transportation / Fuel (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_transport" id="commit_transport" class="form-control" placeholder="e.g. 3000.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Loan EMIs / Debt (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="commit_loan" id="commit_loan" class="form-control" placeholder="e.g. 4000.00">
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="goToStep(2)">&larr; Back</button>
                <button type="button" class="btn btn-primary" onclick="goToStep(4)">Next: Financial Goals &rarr;</button>
            </div>
        </div>

        <!-- STEP 4: Financial Goals -->
        <div class="card wizard-step" id="step-4">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i data-lucide="target" style="color: #8b5cf6;"></i> Step 4: Savings Goals & Strategy</h3>
                    <div class="card-subtitle">Set your savings priority and monthly wealth accumulation targets.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Savings Style Strategy</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div class="option-card" onclick="selectSavingsStyle('conservative', 0.10)" id="style-conservative">
                        <strong style="color: var(--info); display: block; margin-bottom: 4px;">Conservative</strong>
                        <div style="font-size: 0.76rem; color: var(--text-muted);">Save ~10% of salary. Great for tight budgets.</div>
                    </div>
                    <div class="option-card selected" onclick="selectSavingsStyle('balanced', 0.20)" id="style-balanced">
                        <strong style="color: var(--success); display: block; margin-bottom: 4px;">Balanced (50/30/20)</strong>
                        <div style="font-size: 0.76rem; color: var(--text-muted);">Save ~20% of salary. Recommended standard.</div>
                    </div>
                    <div class="option-card" onclick="selectSavingsStyle('ambitious', 0.30)" id="style-ambitious">
                        <strong style="color: #8b5cf6; display: block; margin-bottom: 4px;">Ambitious</strong>
                        <div style="font-size: 0.76rem; color: var(--text-muted);">Save ~30%+ of salary for rapid wealth building.</div>
                    </div>
                </div>
                <input type="hidden" name="savings_style" id="savings_style" value="balanced">
            </div>

            <div class="form-group">
                <label class="form-label">Target Monthly Savings (<?= $currency ?>)</label>
                <input type="number" step="0.01" min="0" name="savings_target" id="savings_target" class="form-control" placeholder="Auto-calculated or enter custom">
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 20px;">
                <h4 style="font-size: 0.95rem; margin-bottom: 12px; color: var(--text-primary);">Create Your First Savings Goal:</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Goal Name</label>
                        <input type="text" name="goal_name" id="goal_name" class="form-control" value="Emergency Fund">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Goal Amount (<?= $currency ?>)</label>
                        <input type="number" step="0.01" min="1000" name="goal_target" id="goal_target" class="form-control" placeholder="e.g. 100000.00">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="goToStep(3)">&larr; Back</button>
                <button type="button" class="btn btn-primary" onclick="generateAndPreviewPlan()">Preview My Plan &rarr;</button>
            </div>
        </div>

        <!-- STEP 5: Personalized Plan Preview & Confirmation -->
        <div class="card wizard-step" id="step-5">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i data-lucide="check-circle-2" style="color: var(--success);"></i> Step 5: Your Personalized Financial Plan</h3>
                    <div class="card-subtitle">Here is how SmartSpend recommends budgeting your monthly income:</div>
                </div>
            </div>

            <div id="planSummaryBox" style="margin-bottom: 24px;">
                <!-- Populated dynamically by JS -->
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="goToStep(4)">&larr; Back to Adjust</button>
                <button type="submit" class="btn btn-success btn-lg">
                    <i data-lucide="sparkles" style="width: 18px; height: 18px;"></i>
                    Confirm Plan & Launch Dashboard
                </button>
            </div>
        </div>

    </form>
</div>

<script>
    lucide.createIcons();

    function goToStep(stepNumber) {
        document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
        document.getElementById('step-' + stepNumber).classList.add('active');

        // Update pills
        for (let i = 1; i <= 5; i++) {
            const pill = document.getElementById('pill-' + i);
            pill.classList.remove('active', 'done');
            if (i < stepNumber) {
                pill.classList.add('done');
            } else if (i === stepNumber) {
                pill.classList.add('active');
            }
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function selectSavingsStyle(style, ratio) {
        document.getElementById('savings_style').value = style;
        document.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
        document.getElementById('style-' + style).classList.add('selected');

        const income = parseFloat(document.getElementById('monthly_income').value) || 0;
        if (income > 0) {
            document.getElementById('savings_target').value = (income * ratio).toFixed(2);
            document.getElementById('goal_target').value = (income * 3).toFixed(2);
        }
    }

    function generateAndPreviewPlan() {
        const income = parseFloat(document.getElementById('monthly_income').value) || 0;
        const rent = parseFloat(document.getElementById('commit_rent').value) || 0;
        const groceries = parseFloat(document.getElementById('commit_groceries').value) || 0;
        const utilities = parseFloat(document.getElementById('commit_utilities').value) || 0;
        const internet = parseFloat(document.getElementById('commit_internet').value) || 0;
        const transport = parseFloat(document.getElementById('commit_transport').value) || 0;
        const loan = parseFloat(document.getElementById('commit_loan').value) || 0;
        const savings = parseFloat(document.getElementById('savings_target').value) || (income * 0.2);

        const totalEssentials = rent + groceries + utilities + internet + transport + loan;
        const flexibleSpending = Math.max(0, income - totalEssentials - savings);
        const dailyAllowance = (flexibleSpending / 30).toFixed(2);

        const box = document.getElementById('planSummaryBox');
        box.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px;">
                <div style="padding: 16px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; text-align: center;">
                    <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Expected Income</div>
                    <div style="font-size: 1.35rem; font-weight: 700; color: var(--success); margin-top: 4px;">₹${income.toLocaleString('en-IN')}</div>
                </div>
                <div style="padding: 16px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; text-align: center;">
                    <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Essential Commitments</div>
                    <div style="font-size: 1.35rem; font-weight: 700; color: var(--danger); margin-top: 4px;">₹${totalEssentials.toLocaleString('en-IN')}</div>
                </div>
                <div style="padding: 16px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; text-align: center;">
                    <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Planned Monthly Savings</div>
                    <div style="font-size: 1.35rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">₹${savings.toLocaleString('en-IN')}</div>
                </div>
            </div>

            <div style="padding: 20px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <strong style="color: var(--primary); font-size: 1rem;">Projected Monthly Flexible Spending Allowance</strong>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #fff;">₹${flexibleSpending.toLocaleString('en-IN')}</span>
                </div>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                    Formula: Expected Income (₹${income}) − Essential Costs (₹${totalEssentials}) − Planned Savings (₹${savings}) = <strong>₹${flexibleSpending}</strong>.
                    This equates to a safe discretionary spending limit of <strong>₹${dailyAllowance} per day</strong> for dining out, shopping, and entertainment.
                </p>
            </div>
        `;

        goToStep(5);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
</script>
</body>
</html>
