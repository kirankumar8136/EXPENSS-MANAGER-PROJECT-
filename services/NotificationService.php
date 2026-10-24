<?php
/**
 * Notification Center & Bill Reminder Service
 * Scans upcoming and overdue bills, generates persistent database notifications,
 * prevents duplicates, and delivers unread counts and alerts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Process upcoming and overdue bill reminders.
     * Can run for a specific user (e.g. at user login) or all users (e.g. cron CLI).
     *
     * @param int|null $targetUserId
     * @return int Number of newly created notifications
     */
    public function generateBillReminders(?int $targetUserId = null): int
    {
        $today = new DateTime('today');
        $todayStr = $today->format('Y-m-d');
        $createdCount = 0;

        // Fetch active bills that are not already paid for this occurrence
        $sql = '
            SELECT b.*, u.currency, us.reminder_days_before
            FROM recurring_bills b
            JOIN users u ON u.id = b.user_id
            LEFT JOIN user_settings us ON us.user_id = b.user_id
            WHERE b.status = "active"
              AND NOT EXISTS (
                  SELECT 1 FROM bill_payments p
                  WHERE p.bill_id = b.id AND p.due_date = b.next_due_date
              )
        ';
        if ($targetUserId !== null) {
            $sql .= ' AND b.user_id = :uid';
        }

        $stmt = $this->pdo->prepare($sql);
        if ($targetUserId !== null) {
            $stmt->execute(['uid' => $targetUserId]);
        } else {
            $stmt->execute();
        }
        $bills = $stmt->fetchAll();

        foreach ($bills as $bill) {
            $dueDate = new DateTime($bill['next_due_date']);
            $diffDays = (int) $today->diff($dueDate)->format('%r%a');
            $currency = $bill['currency'] ?? '₹';
            $amountFormatted = formatCurrency($bill['amount'], $currency);

            // User reminder preferences (e.g. "7,3,1,0")
            $prefStr = $bill['reminder_days_before'] ?: '7,3,1,0';
            $prefDays = array_map('intval', explode(',', $prefStr));

            // 1. Check upcoming reminders
            if ($diffDays >= 0 && in_array($diffDays, $prefDays, true)) {
                $uniqueKey = sprintf('bill_%d_%s_day_%d', $bill['id'], $bill['next_due_date'], $diffDays);
                
                if ($diffDays === 0) {
                    $title = "Bill Due Today: {$bill['name']}";
                    $msg = "Your bill for {$bill['name']} ({$amountFormatted}) is due today! Mark it as paid once completed.";
                } elseif ($diffDays === 1) {
                    $title = "Bill Due Tomorrow: {$bill['name']}";
                    $msg = "Reminder: {$bill['name']} ({$amountFormatted}) is due tomorrow.";
                } else {
                    $title = "Upcoming Bill: {$bill['name']}";
                    $msg = "Your {$bill['name']} payment of {$amountFormatted} is due in {$diffDays} days ({$dueDate->format('d M')}).";
                }

                if ($this->insertNotificationIfNew((int) $bill['user_id'], $title, $msg, 'bill_due', (int) $bill['id'], 'bills.php', $uniqueKey)) {
                    $createdCount++;
                }
            }

            // 2. Check overdue reminders
            if ($diffDays < 0) {
                $daysOverdue = abs($diffDays);
                // Trigger overdue alert on day 1, 3, 7 of overdue
                if ($daysOverdue === 1 || $daysOverdue === 3 || $daysOverdue % 7 === 0) {
                    $uniqueKey = sprintf('bill_%d_%s_overdue_%d', $bill['id'], $bill['next_due_date'], $daysOverdue);
                    $title = "Overdue Bill Alert: {$bill['name']}";
                    $msg = "URGENT: {$bill['name']} ({$amountFormatted}) is overdue by {$daysOverdue} " . ($daysOverdue === 1 ? 'day' : 'days') . ". Please record your payment.";

                    if ($this->insertNotificationIfNew((int) $bill['user_id'], $title, $msg, 'bill_overdue', (int) $bill['id'], 'bills.php', $uniqueKey)) {
                        $createdCount++;
                    }
                }
            }
        }

        return $createdCount;
    }

    /**
     * Helper to insert a notification with duplicate key protection.
     */
    private function insertNotificationIfNew(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $billId,
        ?string $link,
        string $uniqueKey
    ): bool {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO notifications (user_id, title, message, type, bill_id, link, unique_identifier)
                VALUES (:uid, :title, :msg, :type, :bid, :link, :ukey)
                ON DUPLICATE KEY UPDATE id = id
            ');
            $stmt->execute([
                'uid'   => $userId,
                'title' => $title,
                'msg'   => $message,
                'type'  => $type,
                'bid'   => $billId,
                'link'  => $link,
                'ukey'  => $uniqueKey,
            ]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Error creating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get count of unread notifications for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get notifications list for a user.
     */
    public function getNotifications(int $userId, int $limit = 25): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM notifications 
            WHERE user_id = :uid 
            ORDER BY is_read ASC, created_at DESC 
            LIMIT :lim
        ');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        return $stmt->execute(['id' => $notificationId, 'uid' => $userId]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid');
        return $stmt->execute(['uid' => $userId]);
    }
}
