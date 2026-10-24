<?php
/**
 * Global Utility Functions
 */

declare(strict_types=1);

/**
 * Format a number into currency with appropriate symbol and thousands separators.
 *
 * @param float|int|string $amount
 * @param string $symbol
 * @param int $decimals
 * @return string
 */
function formatCurrency($amount, string $symbol = '₹', int $decimals = 2): string
{
    $val = (float) $amount;
    $formatted = number_format(abs($val), $decimals, '.', ',');
    if ($val < 0) {
        return '-' . $symbol . $formatted;
    }
    return $symbol . $formatted;
}

/**
 * Sanitize string for HTML output.
 *
 * @param mixed $data
 * @return string
 */
function sanitize($data): string
{
    return htmlspecialchars((string) ($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Set a flash notification message.
 *
 * @param string $type ('success', 'danger', 'warning', 'info')
 * @param string $message
 */
function flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear flash notification messages.
 *
 * @return array
 */
function getFlashMessages(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Redirect safely to another page.
 *
 * @param string $url
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Return JSON response and terminate.
 *
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Format date string safely.
 *
 * @param string|null $date
 * @param string $format
 * @return string
 */
function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date)) {
        return '—';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string) $date;
    }
}

/**
 * Format relative days (e.g. "Due today", "Overdue by 2 days", "In 3 days").
 *
 * @param string $dueDate
 * @return array ['text' => string, 'class' => string, 'days' => int]
 */
function getDueStatus(string $dueDate): array
{
    $today = new DateTime('today');
    $due = new DateTime($dueDate);
    $interval = $today->diff($due);
    $days = (int) $interval->format('%r%a');

    if ($days < 0) {
        return [
            'text'  => 'Overdue by ' . abs($days) . ' ' . (abs($days) === 1 ? 'day' : 'days'),
            'class' => 'status-danger',
            'days'  => $days,
        ];
    } elseif ($days === 0) {
        return [
            'text'  => 'Due today',
            'class' => 'status-warning',
            'days'  => 0,
        ];
    } elseif ($days === 1) {
        return [
            'text'  => 'Due tomorrow',
            'class' => 'status-warning',
            'days'  => 1,
        ];
    } elseif ($days <= 7) {
        return [
            'text'  => 'Due in ' . $days . ' days',
            'class' => 'status-warning',
            'days'  => $days,
        ];
    } else {
        return [
            'text'  => 'Due on ' . $due->format('d M'),
            'class' => 'status-neutral',
            'days'  => $days,
        ];
    }
}

/**
 * Convert payment method enum key to user-friendly label.
 *
 * @param string $key
 * @return string
 */
function getPaymentMethodLabel(string $key): string
{
    $map = [
        'upi'           => 'UPI (GPay / PhonePe / Paytm)',
        'bank_transfer' => 'Bank Transfer (NEFT/IMPS)',
        'credit_card'   => 'Credit Card',
        'debit_card'    => 'Debit Card',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
        'other'         => 'Other',
    ];
    return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

/**
 * Convert recurrence frequency to readable string.
 *
 * @param string $freq
 * @return string
 */
function getFrequencyLabel(string $freq): string
{
    $map = [
        'weekly'      => 'Weekly',
        'monthly'     => 'Monthly',
        'bi_monthly'  => 'Every 2 Months',
        'quarterly'   => 'Quarterly (3 Months)',
        'half_yearly' => 'Half-Yearly (6 Months)',
        'yearly'      => 'Yearly (Annual)',
    ];
    return $map[$freq] ?? ucfirst(str_replace('_', ' ', $freq));
}

/**
 * Safely compute percentage without division by zero.
 *
 * @param float|int $numerator
 * @param float|int $denominator
 * @param int $decimals
 * @return float
 */
function getSafePercent($numerator, $denominator, int $decimals = 1): float
{
    if ((float)$denominator <= 0) {
        return 0.0;
    }
    return round(((float)$numerator / (float)$denominator) * 100, $decimals);
}
