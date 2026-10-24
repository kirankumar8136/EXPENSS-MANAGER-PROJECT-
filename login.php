<?php
/**
 * SmartSpend User Login
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

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);

                if (empty($user['onboarding_completed'])) {
                    redirect('onboarding.php');
                } else {
                    flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    redirect('dashboard.php');
                }
            } else {
                $error = 'Invalid email address or password.';
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'A database connection error occurred. Please ensure MySQL is running in XAMPP.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — SmartSpend</title>
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
            max-width: 440px;
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 36px 32px;
            box-shadow: var(--shadow-lg);
        }
        .demo-box {
            margin-top: 20px;
            padding: 12px 16px;
            background-color: rgba(99, 102, 241, 0.08);
            border: 1px dashed rgba(99, 102, 241, 0.3);
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        <h2 style="text-align: center; margin-bottom: 6px;">Sign In to Your Account</h2>
        <p style="text-align: center; font-size: 0.85rem; margin-bottom: 24px;">Smart Expense Tracker & Personal Money Manager</p>

        <?php if (!empty($error)): ?>
            <div class="alert-banner danger">
                <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" required autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                </div>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                Sign In &rarr;
            </button>
        </form>

        <!-- Quick Demo Credentials Fill -->
        <div class="demo-box">
            <div>
                <strong style="color: var(--primary);">Evaluation Demo Account</strong>
                <div style="color: var(--text-muted); font-size: 0.76rem;">demo@smartspend.io / password123</div>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="fillDemoCredentials()">
                Auto-fill
            </button>
        </div>

        <div style="text-align: center; margin-top: 24px; font-size: 0.85rem; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="font-weight: 600;">Create Account</a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    function fillDemoCredentials() {
        document.getElementById('email').value = 'demo@smartspend.io';
        document.getElementById('password').value = 'password123';
    }
</script>
</body>
</html>
