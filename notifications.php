<?php
/**
 * SmartSpend Persistent Notification Center
 */

declare(strict_types=1);

$pageTitle = 'Notification Center';
$activePage = 'notifications';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/services/NotificationService.php';

$pdo = getDbConnection();
$notifService = new NotificationService($pdo);

// Trigger background check for today's reminders
$notifService->generateBillReminders($userId);

$filter = $_GET['filter'] ?? 'all';
$sql = 'SELECT * FROM notifications WHERE user_id = :uid';
if ($filter === 'unread') {
    $sql .= ' AND is_read = 0';
}
$sql .= ' ORDER BY is_read ASC, created_at DESC LIMIT 50';

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $userId]);
$notifications = $stmt->fetchAll();
$unreadCount = $notifService->getUnreadCount($userId);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="notifications.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                All (<?= count($notifications) ?>)
            </a>
            <a href="notifications.php?filter=unread" class="btn <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                Unread (<?= $unreadCount ?>)
            </a>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form action="actions/notification_actions.php" method="POST" style="margin: 0;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline btn-sm">
                    <i data-lucide="check-check" style="width: 14px; height: 14px;"></i> Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="bell" style="color: var(--primary);"></i> Notification Feed</h3>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
            <div class="empty-state-title">No Notifications</div>
            <div class="empty-state-text">You have no <?= $filter === 'unread' ? 'unread' : '' ?> alerts right now.</div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column;">
            <?php foreach ($notifications as $n): ?>
                <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; background: <?= $n['is_read'] ? 'transparent' : 'rgba(99, 102, 241, 0.04)' ?>;">
                    <div style="display: flex; align-items: flex-start; gap: 14px;">
                        <div class="notif-icon <?= sanitize($n['type']) ?>" style="margin-top: 2px;">
                            <?php if ($n['type'] === 'bill_overdue'): ?>
                                <i data-lucide="alert-octagon"></i>
                            <?php elseif ($n['type'] === 'bill_due'): ?>
                                <i data-lucide="calendar"></i>
                            <?php elseif ($n['type'] === 'budget_warning'): ?>
                                <i data-lucide="alert-triangle"></i>
                            <?php else: ?>
                                <i data-lucide="bell"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h4 style="font-size: 0.95rem; color: var(--text-primary); margin: 0;"><?= sanitize($n['title']) ?></h4>
                                <?php if (!$n['is_read']): ?>
                                    <span class="badge badge-warning" style="font-size: 0.68rem;">New</span>
                                <?php endif; ?>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 4px 0 6px; line-height: 1.4;">
                                <?= sanitize($n['message']) ?>
                            </p>
                            <div style="font-size: 0.74rem; color: var(--text-muted); display: flex; align-items: center; gap: 12px;">
                                <span><?= formatDate($n['created_at'], 'd M Y, h:i A') ?></span>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?= sanitize($n['link']) ?>" style="color: var(--primary); font-weight: 600;">Go to related bill &rarr;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$n['is_read']): ?>
                        <a href="actions/notification_actions.php?action=mark_read&id=<?= $n['id'] ?>" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 0.75rem; white-space: nowrap;">
                            Mark as read
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
