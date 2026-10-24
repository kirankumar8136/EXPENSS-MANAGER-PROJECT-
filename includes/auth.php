<?php
/**
 * Authentication and Session Management
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Require login. Redirects to login page if unauthenticated.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('warning', 'Please log in to access your financial dashboard.');
        redirect('login.php');
    }
}

/**
 * Get the currently authenticated user's ID.
 *
 * @return int|null
 */
function getCurrentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Retrieve user record from database.
 *
 * @param int|null $userId
 * @return array|null
 */
function getCurrentUser(?int $userId = null): ?array
{
    static $cachedUser = null;
    $id = $userId ?? getCurrentUserId();

    if ($id === null) {
        return null;
    }

    if ($cachedUser !== null && $cachedUser['id'] === $id) {
        return $cachedUser;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, email, currency, currency_code, onboarding_completed, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if ($user) {
            $cachedUser = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log('Error fetching current user: ' . $e->getMessage());
    }

    return null;
}

/**
 * Retrieve user settings & financial profile.
 *
 * @param int $userId
 * @return array
 */
function getUserSettings(int $userId): array
{
    static $cachedSettings = [];

    if (isset($cachedSettings[$userId])) {
        return $cachedSettings[$userId];
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $settings = $stmt->fetch();

        if (!$settings) {
            // Default placeholder settings
            $settings = [
                'user_id'                => $userId,
                'monthly_income'         => 0.00,
                'income_source_type'     => 'salaried',
                'income_frequency'       => 'monthly',
                'salary_day'             => 1,
                'is_income_fixed'        => 1,
                'monthly_savings_target' => 0.00,
                'savings_style'          => 'balanced',
                'budget_period_type'     => 'calendar_month',
                'reminder_days_before'   => '7,3,1,0',
                'theme'                  => 'dark',
            ];
        }

        $cachedSettings[$userId] = $settings;
        return $settings;
    } catch (Exception $e) {
        error_log('Error fetching user settings: ' . $e->getMessage());
        return [];
    }
}

/**
 * Log a user in and regenerate session ID to prevent session fixation.
 *
 * @param array $user
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['currency'] = $user['currency'] ?? '₹';
    $_SESSION['onboarding_completed'] = (bool) ($user['onboarding_completed'] ?? 0);
}

/**
 * Securely log out the current user and wipe session cookies.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
