<?php
/**
 * Recurring Bill Management & Recurrence Calculator Engine
 * Handles accurate interval calculations, month-end date preservation (e.g. 31st),
 * leap-year adjustments, and atomic bill payment processing with duplicate prevention.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class BillRecurrence
{
    private PDO $pdo;
    private int $userId;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }

    /**
     * Compute the next due date following a given reference date,
     * honoring the original intended due day (e.g. 31st).
     *
     * @param string $currentDueDate YYYY-MM-DD
     * @param string $frequency 'weekly', 'monthly', 'bi_monthly', 'quarterly', 'half_yearly', 'yearly'
     * @param int $targetDueDay 1 to 31
     * @return string YYYY-MM-DD
     */
    public static function calculateNextDueDate(string $currentDueDate, string $frequency, int $targetDueDay): string
    {
        $dt = new DateTime($currentDueDate);

        if ($frequency === 'weekly') {
            $dt->modify('+7 days');
            return $dt->format('Y-m-d');
        }

        $monthsToAdd = match ($frequency) {
            'monthly'     => 1,
            'bi_monthly'  => 2,
            'quarterly'   => 3,
            'half_yearly' => 6,
            'yearly'      => 12,
            default       => 1,
        };

        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');

        $totalMonths = ($year * 12 + ($month - 1)) + $monthsToAdd;
        $nextYear = (int) floor($totalMonths / 12);
        $nextMonth = ($totalMonths % 12) + 1;

        // Find maximum days in target month
        $maxDays = (int) (new DateTime(sprintf('%04d-%02d-01', $nextYear, $nextMonth)))->format('t');
        $actualDay = min($targetDueDay, $maxDays);

        return sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $actualDay);
    }

    /**
     * Get categorized bills for the bills dashboard.
     */
    public function getCategorizedBills(): array
    {
        $todayStr = (new DateTime('today'))->format('Y-m-d');
        $endOfMonth = (new DateTime('last day of this month'))->format('Y-m-d');
        $sevenDaysStr = (new DateTime('+7 days'))->format('Y-m-d');

        $stmt = $this->pdo->prepare('
            SELECT b.*, c.name as category_name, c.color as category_color, c.icon as category_icon,
                   (SELECT COUNT(*) FROM bill_payments p WHERE p.bill_id = b.id AND p.due_date = b.next_due_date) as is_paid_this_cycle
            FROM recurring_bills b
            JOIN categories c ON c.id = b.category_id
            WHERE b.user_id = :uid AND b.status != "archived"
            ORDER BY b.next_due_date ASC
        ');
        $stmt->execute(['uid' => $this->userId]);
        $allBills = $stmt->fetchAll();

        $categories = [
            'due_today'        => [],
            'due_soon'         => [], // within 7 days
            'due_later_month'  => [],
            'overdue'          => [],
            'upcoming_yearly'  => [],
            'paid_or_future'   => [],
        ];

        $totals = [
            'overdue'        => 0.0,
            'due_this_month' => 0.0,
            'monthly_run_rate' => 0.0,
            'yearly_run_rate'  => 0.0,
        ];

        foreach ($allBills as $bill) {
            $amt = (float) $bill['amount'];
            $dueDate = $bill['next_due_date'];
            $isPaid = (bool) $bill['is_paid_this_cycle'];
            $freq = $bill['frequency'];

            // Compute run-rate commitments
            if ($bill['status'] === 'active') {
                $monthlyEquiv = match ($freq) {
                    'weekly'      => $amt * 4.333,
                    'monthly'     => $amt,
                    'bi_monthly'  => $amt / 2,
                    'quarterly'   => $amt / 3,
                    'half_yearly' => $amt / 6,
                    'yearly'      => $amt / 12,
                    default       => $amt,
                };
                $totals['monthly_run_rate'] += $monthlyEquiv;
                $totals['yearly_run_rate'] += ($monthlyEquiv * 12);
            }

            if ($freq === 'yearly' && $dueDate > $endOfMonth) {
                $categories['upcoming_yearly'][] = $bill;
                continue;
            }

            if ($isPaid) {
                $categories['paid_or_future'][] = $bill;
            } elseif ($dueDate < $todayStr) {
                $categories['overdue'][] = $bill;
                $totals['overdue'] += $amt;
            } elseif ($dueDate === $todayStr) {
                $categories['due_today'][] = $bill;
                $totals['due_this_month'] += $amt;
            } elseif ($dueDate <= $sevenDaysStr) {
                $categories['due_soon'][] = $bill;
                $totals['due_this_month'] += $amt;
            } elseif ($dueDate <= $endOfMonth) {
                $categories['due_later_month'][] = $bill;
                $totals['due_this_month'] += $amt;
            } else {
                $categories['paid_or_future'][] = $bill;
            }
        }

        $totals['monthly_run_rate'] = round($totals['monthly_run_rate'], 2);
        $totals['yearly_run_rate'] = round($totals['yearly_run_rate'], 2);
        $totals['overdue'] = round($totals['overdue'], 2);
        $totals['due_this_month'] = round($totals['due_this_month'], 2);

        return [
            'groups' => $categories,
            'totals' => $totals,
            'all'    => $allBills,
        ];
    }

    /**
     * Get all bills for a specific calendar month (for Calendar view).
     */
    public function getBillsForCalendarMonth(int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = (new DateTime($startDate))->format('Y-m-t');

        // Include both active recurring bills and recorded bill payments for this month
        $stmt = $this->pdo->prepare('
            SELECT b.id, b.name, b.amount, b.next_due_date as date, b.frequency,
                   c.name as category_name, c.color as category_color,
                   CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as is_paid,
                   p.paid_date, p.amount_paid
            FROM recurring_bills b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN bill_payments p ON p.bill_id = b.id AND p.due_date = b.next_due_date
            WHERE b.user_id = :uid
              AND b.next_due_date BETWEEN :start AND :end
            ORDER BY b.next_due_date ASC
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Record a bill payment with full DB transaction:
     * 1. Check duplicate payment for this (bill_id, due_date)
     * 2. Insert bill_payments record
     * 3. Insert linked transaction record (type = 'expense')
     * 4. Link transaction_id back into bill_payments
     * 5. Calculate and advance next_due_date on recurring_bills
     *
     * @param int $billId
     * @param float $amountPaid
     * @param string $paidDate YYYY-MM-DD
     * @param string $paymentMethod
     * @param string|null $paymentRef
     * @param string|null $notes
     * @return array ['success' => bool, 'message' => string]
     */
    public function recordBillPayment(
        int $billId,
        float $amountPaid,
        string $paidDate,
        string $paymentMethod = 'upi',
        ?string $paymentRef = null,
        ?string $notes = null
    ): array {
        // Fetch bill details and ownership
        $stmt = $this->pdo->prepare('SELECT * FROM recurring_bills WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $billId, 'uid' => $this->userId]);
        $bill = $stmt->fetch();

        if (!$bill) {
            return ['success' => false, 'message' => 'Bill not found or unauthorized.'];
        }

        $currentDueDate = $bill['next_due_date'];

        // Begin Transaction
        $this->pdo->beginTransaction();

        try {
            // Check if already paid for this occurrence
            $stmt = $this->pdo->prepare('SELECT id FROM bill_payments WHERE bill_id = :bid AND due_date = :due LIMIT 1');
            $stmt->execute(['bid' => $billId, 'due' => $currentDueDate]);
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'This bill occurrence has already been recorded as paid.'];
            }

            // 1. Insert bill_payments
            $stmt = $this->pdo->prepare('
                INSERT INTO bill_payments (bill_id, user_id, due_date, paid_date, amount_paid, payment_method, payment_reference, notes)
                VALUES (:bid, :uid, :due, :paid, :amt, :method, :ref, :notes)
            ');
            $stmt->execute([
                'bid'    => $billId,
                'uid'    => $this->userId,
                'due'    => $currentDueDate,
                'paid'   => $paidDate,
                'amt'    => $amountPaid,
                'method' => $paymentMethod,
                'ref'    => $paymentRef,
                'notes'  => $notes,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();

            // 2. Insert linked expense transaction
            $description = 'Bill Payment: ' . $bill['name'];
            $stmt = $this->pdo->prepare('
                INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date, payment_method, notes, bill_payment_id)
                VALUES (:uid, :cid, "expense", :amt, :desc, :tdate, :method, :notes, :pid)
            ');
            $stmt->execute([
                'uid'    => $this->userId,
                'cid'    => $bill['category_id'],
                'amt'    => $amountPaid,
                'desc'   => $description,
                'tdate'  => $paidDate,
                'method' => $paymentMethod,
                'notes'  => $notes ? ($notes . ' (Ref: ' . $paymentRef . ')') : ($paymentRef ? 'Ref: ' . $paymentRef : null),
                'pid'    => $paymentId,
            ]);
            $transactionId = (int) $this->pdo->lastInsertId();

            // 3. Link transaction_id back into bill_payments
            $stmt = $this->pdo->prepare('UPDATE bill_payments SET transaction_id = :tid WHERE id = :pid');
            $stmt->execute(['tid' => $transactionId, 'pid' => $paymentId]);

            // 4. Calculate next recurrence date
            $nextDueDate = self::calculateNextDueDate($currentDueDate, $bill['frequency'], (int) $bill['due_day']);

            // Check if end_date reached
            $newStatus = $bill['status'];
            if (!empty($bill['end_date']) && $nextDueDate > $bill['end_date']) {
                $newStatus = 'archived';
            }

            // 5. Advance bill next due date
            $stmt = $this->pdo->prepare('UPDATE recurring_bills SET next_due_date = :next_due, status = :st, updated_at = NOW() WHERE id = :bid');
            $stmt->execute([
                'next_due' => $nextDueDate,
                'st'       => $newStatus,
                'bid'      => $billId,
            ]);

            $this->pdo->commit();
            return [
                'success'       => true,
                'message'       => 'Bill payment recorded successfully and linked to expenses.',
                'next_due_date' => $nextDueDate,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Bill payment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while processing payment: ' . $e->getMessage()];
        }
    }

    /**
     * Get payment history for a specific bill or for all user bills.
     */
    public function getPaymentHistory(?int $billId = null, int $limit = 50): array
    {
        $sql = '
            SELECT p.*, b.name as bill_name, c.name as category_name, c.color as category_color
            FROM bill_payments p
            JOIN recurring_bills b ON b.id = p.bill_id
            JOIN categories c ON c.id = b.category_id
            WHERE p.user_id = :uid
        ';
        if ($billId !== null) {
            $sql .= ' AND p.bill_id = :bid';
        }
        $sql .= ' ORDER BY p.paid_date DESC, p.id DESC LIMIT :lim';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uid', $this->userId, PDO::PARAM_INT);
        if ($billId !== null) {
            $stmt->bindValue(':bid', $billId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
