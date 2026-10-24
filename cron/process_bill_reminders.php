<?php
/**
 * SmartSpend Background Bill Reminder Processor
 *
 * This CLI script is designed to be executed periodically (e.g. daily at 8:00 AM)
 * via Windows Task Scheduler or Linux Cron.
 *
 * Usage:
 *   php cron/process_bill_reminders.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'smartspend_cron_secret')) {
    // Only allow CLI or authorized webhooks
    http_response_code(403);
    die("Access denied. This background processor is meant to be run via CLI or scheduled task.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting SmartSpend Bill Reminder Background Worker...\n";

try {
    $pdo = getDbConnection();
    $notificationService = new NotificationService($pdo);

    // Process all users across the database
    $created = $notificationService->generateBillReminders(null);

    $duration = round(microtime(true) - $startTime, 4);
    echo "[{$timestamp}] Completed successfully in {$duration}s.\n";
    echo "[{$timestamp}] Generated {$created} new reminder notification(s).\n";
    exit(0);
} catch (Throwable $e) {
    echo "[{$timestamp}] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
