<?php
/**
 * SmartSpend User Registration
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfOrDie();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $currency = trim($_POST['currency'] ?? '₹');
    $currencyCode = $currency === '$' ? 'USD' : ($currency === '€' ? 'EUR' : ($currency === '£' ? 'GBP' : 'INR'));

    if (empty($fullName) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (strlen($fullName) < 2) {
        $error = 'Please enter a valid full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirmation password do not match.';
    } else {
        try {
            $pdo = getDbConnection();

            // Check duplicate email
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email address already exists. Please log in.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // 1. Create User
                $stmt = $pdo->prepare('
                    INSERT INTO users (full_name, email, password_hash, currency, currency_code, onboarding_completed)
                    VALUES (:name, :email, :hash, :cur, :curcode, 0)
                ');
                $stmt->execute([
                    'name'    => $fullName,
                    'email'   => $email,
                    'hash'    => $hash,
                    'cur'     => $currency,
                    'curcode' => $currencyCode,
                ]);
                $newUserId = (int) $pdo->lastInsertId();

                // 2. Initialize default user settings
                $stmt = $pdo->prepare('
                    INSERT INTO user_settings (user_id, monthly_income, income_source_type, income_frequency, salary_day, is_income_fixed, monthly_savings_target, savings_style, budget_period_type)
                    VALUES (:uid, 0.00, "salaried", "monthly", 1, 1, 0.00, "balanced", "calendar_month")
                ');
                $stmt->execute(['uid' => $newUserId]);

                // 3. Initialize default opening balance
                $stmt = $pdo->prepare('
                    INSERT INTO opening_balances (user_id, amount, as_of_date)
                    VALUES (:uid, 0.00, :today)
                ');
                $stmt->execute(['uid' => $newUserId, 'today' => date('Y-m-d')]);

                $pdo->commit();

                // Auto login
                $newUser = [
                    'id'                   => $newUserId,
                    'full_name'            => $fullName,
                    'email'                => $email,
                    'currency'             => $currency,
                    'currency_code'        => $currencyCode,
                    'onboarding_completed' => 0,
                ];
                loginUser($newUser);

                flash('success', 'Account created successfully! Welcome to your SmartSpend onboarding setup.');
                redirect('onboarding.php');
            }
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Free Account — SmartSpend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, var(--bg-base) 70%);
            padding: 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 480px;
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 36px 32px;
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Logo -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px;">
            <div class="brand-icon" style="width: 42px; height: 42px;">
                <i data-lucide="wallet-cards" style="width: 24px; height: 24px;"></i>
            </div>
            <span style="font-size: 1.5rem; font-weight: 800; color: #fff;">SmartSpend</span>
        </div>

        <h2 style="text-align: center; margin-bottom: 6px;">Start Your Financial Journey</h2>
        <p style="text-align: center; font-size: 0.85rem; margin-bottom: 24px;">Personalized salary budgeting, bill tracker & insights</p>

        <?php if (!empty($error)): ?>
            <div class="alert-banner danger">
                <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control" placeholder="e.g. Kiran Sharma" value="<?= sanitize($_POST['full_name'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 chars" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="currency">Default Currency</label>
                <select name="currency" id="currency" class="form-select">
                    <option value="₹" selected>Indian Rupee (₹ INR)</option>
                    <option value="$">US Dollar ($ USD)</option>
                    <option value="€">Euro (€ EUR)</option>
                    <option value="£">British Pound (£ GBP)</option>
                    <option value="AED ">UAE Dirham (AED)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                Continue to Financial Setup &rarr;
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.85rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign In</a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
