<?php
/**
 * CSRF Protection Utilities
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or retrieve the current session's CSRF token.
 *
 * @return string
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output an HTML hidden input containing the CSRF token.
 *
 * @return string
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify a submitted CSRF token.
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Guard state-changing requests against CSRF.
 * Terminates with HTTP 403 if invalid.
 */
function validateCsrfOrDie(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Security token expired or invalid. Please refresh the page.']);
        } else {
            die('Invalid or expired security token (CSRF). Please go back and refresh the page.');
        }
        exit;
    }
}
