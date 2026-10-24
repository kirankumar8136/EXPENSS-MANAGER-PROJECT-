<?php
/**
 * SmartSpend Master Header Template
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../services/NotificationService.php';

$currentUser = getCurrentUser();
$userId = getCurrentUserId();
$currency = $currentUser['currency'] ?? '₹';

// Retrieve notifications
$unreadCount = 0;
$recentNotifications = [];
if ($userId) {
    try {
        $pdo = getDbConnection();
        $notifService = new NotificationService($pdo);
        // Also check if any bill notifications need to be generated for today
        $notifService->generateBillReminders($userId);
        $unreadCount = $notifService->getUnreadCount($userId);
        $recentNotifications = $notifService->getNotifications($userId, 5);
    } catch (Exception $e) {
        error_log('Notification header error: ' . $e->getMessage());
    }
}

$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> — SmartSpend Personal Money Manager</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="theme-dark">
<div class="app-container">

    <!-- Left Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        
        <!-- Top Navigation Bar -->
        <header class="app-header">
            <div class="header-left">
                <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle Navigation">
                    <i data-lucide="menu"></i>
                </button>
                <h1 class="page-title"><?= sanitize($pageTitle) ?></h1>
            </div>

            <div class="header-right">
                <!-- "Can I Afford This?" Shortcut -->
                <button class="btn btn-outline btn-sm" onclick="openModal('affordabilityCalculatorModal')" title="Can I Afford This?">
                    <i data-lucide="help-circle" style="width: 15px; height: 15px;"></i>
                    <span class="desktop-only">Can I Afford This?</span>
                </button>

                <!-- Quick Add Transaction Shortcut -->
                <button class="btn btn-primary btn-sm" onclick="openModal('quickTransactionModal')" title="Add Transaction">
                    <i data-lucide="plus" style="width: 15px; height: 15px;"></i>
                    <span class="desktop-only">Add Transaction</span>
                </button>

                <!-- Notification Bell & Dropdown -->
                <div style="position: relative;">
                    <button class="icon-btn" id="notificationBtn" aria-label="Notifications">
                        <i data-lucide="bell" style="width: 18px; height: 18px;"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge-dot"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown -->
                    <div class="dropdown-menu" id="notificationMenu">
                        <div class="dropdown-header">
                            <h4>Notifications</h4>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge badge-warning"><?= $unreadCount ?> new</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">All caught up</span>
                            <?php endif; ?>
                        </div>

                        <div class="dropdown-body">
                            <?php if (empty($recentNotifications)): ?>
                                <div style="padding: 24px 18px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                    No notifications right now.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $notif): ?>
                                    <a href="<?= sanitize($notif['link'] ?: 'notifications.php') ?>" class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                                        <div class="notif-icon <?= sanitize($notif['type']) ?>">
                                            <?php if ($notif['type'] === 'bill_overdue'): ?>
                                                <i data-lucide="alert-octagon" style="width: 16px; height: 16px;"></i>
                                            <?php elseif ($notif['type'] === 'bill_due'): ?>
                                                <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                            <?php elseif ($notif['type'] === 'budget_warning'): ?>
                                                <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                                            <?php else: ?>
                                                <i data-lucide="bell" style="width: 16px; height: 16px;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notif-text">
                                            <div class="notif-title"><?= sanitize($notif['title']) ?></div>
                                            <div class="notif-desc"><?= sanitize($notif['message']) ?></div>
                                            <div class="notif-time"><?= formatDate($notif['created_at'], 'd M, h:i A') ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="dropdown-footer">
                            <a href="notifications.php" style="font-size: 0.82rem; font-weight: 600;">View All Notifications &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Avatar -->
                <a href="settings.php" class="user-avatar" title="<?= sanitize($currentUser['full_name'] ?? 'User') ?>">
                    <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                </a>
            </div>
        </header>

        <!-- Page Content Wrapper -->
        <main class="page-content">

            <!-- Flash Alerts -->
            <?php foreach ($flashMessages as $msg): ?>
                <div class="alert-banner <?= sanitize($msg['type']) ?>">
                    <i data-lucide="<?= $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'danger' ? 'alert-triangle' : 'info') ?>" style="width: 18px; height: 18px;"></i>
                    <span><?= sanitize($msg['message']) ?></span>
                </div>
            <?php endforeach; ?>
