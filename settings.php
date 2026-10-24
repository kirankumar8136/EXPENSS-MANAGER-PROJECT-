<?php
/**
 * SmartSpend User Settings & Financial Preferences
 */

declare(strict_types=1);

$pageTitle = 'User Settings';
$activePage = 'settings';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';
$settings = getUserSettings($userId);

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 28px;">

    <!-- 1. Personal Profile & Currency Form -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="user" style="color: var(--primary);"></i> Personal Profile & Currency</h3>
                <div class="card-subtitle">Manage your name and default presentation currency</div>
            </div>
        </div>

        <form action="actions/settings_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address (Read-only)</label>
                    <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Preferred Currency Display</label>
                <select name="currency" class="form-select">
                    <option value="₹" <?= $user['currency'] === '₹' ? 'selected' : '' ?>>Indian Rupee (₹ INR)</option>
                    <option value="$" <?= $user['currency'] === '$' ? 'selected' : '' ?>>US Dollar ($ USD)</option>
                    <option value="€" <?= $user['currency'] === '€' ? 'selected' : '' ?>>Euro (€ EUR)</option>
                    <option value="£" <?= $user['currency'] === '£' ? 'selected' : '' ?>>British Pound (£ GBP)</option>
                    <option value="AED " <?= $user['currency'] === 'AED ' ? 'selected' : '' ?>>UAE Dirham (AED)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Save Profile Changes</button>
        </form>
    </div>

    <!-- 2. Financial Profile & Salary-Based Planning Settings -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="briefcase" style="color: var(--success);"></i> Financial Profile & Salary Cycle</h3>
                <div class="card-subtitle">These parameters drive the Smart Budget and "Can I Afford This?" engine</div>
            </div>
        </div>

        <form action="actions/settings_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_financial_settings">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Monthly Take-Home Income (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="monthly_income" class="form-control" value="<?= (float)($settings['monthly_income'] ?? 0) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Income Frequency</label>
                    <select name="income_frequency" class="form-select">
                        <option value="monthly" <?= ($settings['income_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="biweekly" <?= ($settings['income_frequency'] ?? '') === 'biweekly' ? 'selected' : '' ?>>Bi-Weekly (Every 2 Weeks)</option>
                        <option value="weekly" <?= ($settings['income_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="irregular" <?= ($settings['income_frequency'] ?? '') === 'irregular' ? 'selected' : '' ?>>Irregular / Variable</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Salary / Pay Day of Month (1 - 31)</label>
                    <input type="number" min="1" max="31" name="salary_day" class="form-control" value="<?= (int)($settings['salary_day'] ?? 1) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Income Source Classification</label>
                    <select name="income_source_type" class="form-select">
                        <option value="salaried" <?= ($settings['income_source_type'] ?? '') === 'salaried' ? 'selected' : '' ?>>Salaried Employee</option>
                        <option value="freelancer" <?= ($settings['income_source_type'] ?? '') === 'freelancer' ? 'selected' : '' ?>>Freelancer / Consultant</option>
                        <option value="self_employed" <?= ($settings['income_source_type'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Business / Self-Employed</option>
                        <option value="student" <?= ($settings['income_source_type'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="irregular" <?= ($settings['income_source_type'] ?? '') === 'irregular' ? 'selected' : '' ?>>Irregular / Other</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Monthly Savings Target (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="monthly_savings_target" class="form-control" value="<?= (float)($settings['monthly_savings_target'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Savings Style Strategy</label>
                    <select name="savings_style" class="form-select">
                        <option value="conservative" <?= ($settings['savings_style'] ?? '') === 'conservative' ? 'selected' : '' ?>>Conservative (10% Income)</option>
                        <option value="balanced" <?= ($settings['savings_style'] ?? '') === 'balanced' ? 'selected' : '' ?>>Balanced (20% Income - Standard)</option>
                        <option value="ambitious" <?= ($settings['savings_style'] ?? '') === 'ambitious' ? 'selected' : '' ?>>Ambitious (30%+ Income)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Bill Reminder Schedule (Days Before Due Date)</label>
                <input type="text" name="reminder_days_before" class="form-control" value="<?= sanitize($settings['reminder_days_before'] ?? '7,3,1,0') ?>">
                <small class="form-text">Comma-separated days before deadline when notifications are raised. (Default: 7,3,1,0)</small>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Update Financial Parameters</button>
        </form>
    </div>

    <!-- 3. Security & Password Change -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="lock" style="color: var(--warning);"></i> Security & Password</h3>
                <div class="card-subtitle">Keep your financial data protected with a secure password</div>
            </div>
        </div>

        <form action="actions/settings_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">New Password (Min. 6 chars)</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn btn-outline btn-sm">Change Password</button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
