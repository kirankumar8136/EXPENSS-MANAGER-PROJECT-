<?php
/**
 * User Settings Actions Controller
 * Handles profile updates, password changes, financial parameters, and reminder preferences.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
validateCsrfOrDie();

$userId = getCurrentUserId();
$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $currency = trim($_POST['currency'] ?? '₹');
    $currencyCode = $currency === '$' ? 'USD' : ($currency === '€' ? 'EUR' : ($currency === '£' ? 'GBP' : 'INR'));

    if (strlen($fullName) < 2) {
        flash('danger', 'Name must be at least 2 characters.');
        redirect('../settings.php');
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET full_name = :name, currency = :cur, currency_code = :code, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'name' => $fullName,
            'cur'  => $currency,
            'code' => $currencyCode,
            'id'   => $userId,
        ]);

        $_SESSION['user_name'] = $fullName;
        $_SESSION['currency'] = $currency;

        flash('success', 'Profile and currency preferences updated.');
        redirect('../settings.php');
    } catch (Exception $e) {
        error_log('Update profile error: ' . $e->getMessage());
        flash('danger', 'Error updating profile: ' . $e->getMessage());
        redirect('../settings.php');
    }
}

if ($action === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        flash('danger', 'New password must be at least 6 characters.');
        redirect('../settings.php');
    }
    if ($newPassword !== $confirmPassword) {
        flash('danger', 'New password and confirmation do not match.');
        redirect('../settings.php');
    }

    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            flash('danger', 'Incorrect current password entered.');
            redirect('../settings.php');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $newHash, 'id' => $userId]);

        flash('success', 'Password changed successfully.');
        redirect('../settings.php');
    } catch (Exception $e) {
        error_log('Password change error: ' . $e->getMessage());
        flash('danger', 'Error updating password: ' . $e->getMessage());
        redirect('../settings.php');
    }
}

if ($action === 'update_financial_settings') {
    $monthlyIncome = (float) ($_POST['monthly_income'] ?? 0.0);
    $incomeSource = $_POST['income_source_type'] ?? 'salaried';
    $frequency = $_POST['income_frequency'] ?? 'monthly';
    $salaryDay = max(1, min(31, (int) ($_POST['salary_day'] ?? 1)));
    $isFixed = (int) ($_POST['is_income_fixed'] ?? 1);
    $savingsTarget = (float) ($_POST['monthly_savings_target'] ?? 0.0);
    $savingsStyle = $_POST['savings_style'] ?? 'balanced';
    $reminderDays = trim($_POST['reminder_days_before'] ?? '7,3,1,0');

    try {
        $stmt = $pdo->prepare('
            INSERT INTO user_settings (user_id, monthly_income, income_source_type, income_frequency, salary_day, is_income_fixed, monthly_savings_target, savings_style, reminder_days_before)
            VALUES (:uid, :income, :stype, :freq, :sday, :fixed, :starget, :style, :remind)
            ON DUPLICATE KEY UPDATE
                monthly_income = :income2,
                income_source_type = :stype2,
                income_frequency = :freq2,
                salary_day = :sday2,
                is_income_fixed = :fixed2,
                monthly_savings_target = :starget2,
                savings_style = :style2,
                reminder_days_before = :remind2,
                updated_at = NOW()
        ');
        $stmt->execute([
            'uid'      => $userId,
            'income'   => $monthlyIncome,
            'stype'    => $incomeSource,
            'freq'     => $frequency,
            'sday'     => $salaryDay,
            'fixed'    => $isFixed,
            'starget'  => $savingsTarget,
            'style'    => $savingsStyle,
            'remind'   => $reminderDays,
            'income2'  => $monthlyIncome,
            'stype2'   => $incomeSource,
            'freq2'    => $frequency,
            'sday2'    => $salaryDay,
            'fixed2'   => $isFixed,
            'starget2' => $savingsTarget,
            'style2'   => $savingsStyle,
            'remind2'  => $reminderDays,
        ]);

        flash('success', 'Financial parameters and reminder preferences updated.');
        redirect('../settings.php');
    } catch (Exception $e) {
        error_log('Settings update error: ' . $e->getMessage());
        flash('danger', 'Error updating financial settings: ' . $e->getMessage());
        redirect('../settings.php');
    }
}

redirect('../settings.php');
