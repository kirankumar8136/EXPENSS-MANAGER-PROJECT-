<?php
/**
 * Notification Actions Controller
 * Handles marking notifications as read / mark all as read.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/NotificationService.php';

requireLogin();

$userId = getCurrentUserId();
$pdo = getDbConnection();
$notifService = new NotificationService($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_read') {
    $notifId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($notifId > 0) {
        $notifService->markAsRead($notifId, $userId);
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        jsonResponse(['success' => true]);
    }
    flash('success', 'Notification marked as read.');
    redirect('../notifications.php');
}

if ($action === 'mark_all_read') {
    validateCsrfOrDie();
    $notifService->markAllAsRead($userId);

    flash('success', 'All notifications marked as read.');
    redirect('../notifications.php');
}

redirect('../notifications.php');
