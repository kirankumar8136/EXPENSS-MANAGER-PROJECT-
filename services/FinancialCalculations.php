<?php
/**
 * Financial Calculations Engine
 * Handles accurate monetary sums, cash flow balance, period filtering,
 * and category breakdowns using strict SQL aggregation and high-precision math.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class FinancialCalculations
{
    private PDO $pdo;
    private int $userId;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }

    /**
     * Get start and end date for a period keyword or custom dates.
     * Supported: 'current_month', 'last_month', 'last_30_days', 'year_to_date', 'custom'
     */
    public static function resolvePeriod(string $period = 'current_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $now = new DateTime('now');
        switch ($period) {
            case 'last_month':
                $start = (new DateTime('first day of last month'))->format('Y-m-d');
                $end = (new DateTime('last day of last month'))->format('Y-m-d');
                $label = (new DateTime('first day of last month'))->format('F Y');
                break;

            case 'last_30_days':
                $start = (new DateTime('-30 days'))->format('Y-m-d');
                $end = $now->format('Y-m-d');
                $label = 'Last 30 Days';
                break;

            case 'year_to_date':
                $start = $now->format('Y-01-01');
                $end = $now->format('Y-m-d');
                $label = 'Year to Date (' . $now->format('Y') . ')';
                break;

            case 'custom':
                $start = $customStart ?: $now->format('Y-m-01');
                $end = $customEnd ?: $now->format('Y-m-t');
                $label = (new DateTime($start))->format('d M Y') . ' – ' . (new DateTime($end))->format('d M Y');
                break;

            case 'current_month':
            default:
                $start = $now->format('Y-m-01');
                $end = $now->format('Y-m-t');
                $label = $now->format('F Y');
                break;
        }

        return [
            'period'     => $period,
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => $label
        ];
    }

    /**
     * Total Income for the specified period.
     */
    public function getTotalIncome(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) as total
            FROM transactions
            WHERE user_id = :uid
              AND type = "income"
              AND transaction_date BETWEEN :start AND :end
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total Expenses for the specified period.
     */
    public function getTotalExpenses(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) as total
            FROM transactions
            WHERE user_id = :uid
              AND type = "expense"
              AND transaction_date BETWEEN :start AND :end
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Net Cash Flow for the period (Income - Expenses).
     */
    public function getNetCashFlow(string $startDate, string $endDate): float
    {
        return $this->getTotalIncome($startDate, $endDate) - $this->getTotalExpenses($startDate, $endDate);
    }

    /**
     * Actual Total Account Balance:
     * Opening balance + lifetime income - lifetime expenses.
     */
    public function getActualAccountBalance(): float
    {
        // 1. Get opening balance
        $stmt = $this->pdo->prepare('SELECT COALESCE(amount, 0) FROM opening_balances WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $this->userId]);
        $opening = (float) ($stmt->fetchColumn() ?: 0.0);

        // 2. Lifetime income
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :uid AND type = "income"');
        $stmt->execute(['uid' => $this->userId]);
        $allIncome = (float) $stmt->fetchColumn();

        // 3. Lifetime expenses
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :uid AND type = "expense"');
        $stmt->execute(['uid' => $this->userId]);
        $allExpenses = (float) $stmt->fetchColumn();

        return round($opening + $allIncome - $allExpenses, 2);
    }

    /**
     * Actual savings contributions in the period.
     */
    public function getActualSavingsContributions(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(CASE WHEN type = "deposit" THEN amount ELSE -amount END), 0) as total
            FROM savings_contributions
            WHERE user_id = :uid
              AND date BETWEEN :start AND :end
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return max(0.0, (float) $stmt->fetchColumn());
    }

    /**
     * Total upcoming unpaid bills in the remaining period (or next 30 days).
     */
    public function getUpcomingBillsTotal(string $startDate, string $endDate): float
    {
        $today = (new DateTime('today'))->format('Y-m-d');
        // Filter active bills due between today and $endDate that have not been paid for that due_date
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(b.amount), 0) as total
            FROM recurring_bills b
            WHERE b.user_id = :uid
              AND b.status = "active"
              AND b.next_due_date >= :today
              AND b.next_due_date <= :end_date
              AND NOT EXISTS (
                  SELECT 1 FROM bill_payments p
                  WHERE p.bill_id = b.id AND p.due_date = b.next_due_date
              )
        ');
        $stmt->execute(['uid' => $this->userId, 'today' => $today, 'end_date' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total overdue unpaid bills.
     */
    public function getOverdueBillsTotal(): float
    {
        $today = (new DateTime('today'))->format('Y-m-d');
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(b.amount), 0) as total
            FROM recurring_bills b
            WHERE b.user_id = :uid
              AND b.status = "active"
              AND b.next_due_date < :today
              AND NOT EXISTS (
                  SELECT 1 FROM bill_payments p
                  WHERE p.bill_id = b.id AND p.due_date = b.next_due_date
              )
        ');
        $stmt->execute(['uid' => $this->userId, 'today' => $today]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Expense breakdown by category for charts and reports.
     */
    public function getCategoryExpenseBreakdown(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.name, c.color, c.icon, c.is_essential,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   COUNT(t.id) as transaction_count
            FROM categories c
            LEFT JOIN transactions t ON t.category_id = c.id
                                     AND t.user_id = :uid
                                     AND t.type = "expense"
                                     AND t.transaction_date BETWEEN :start AND :end
            WHERE (c.user_id = :uid OR c.user_id IS NULL)
              AND c.type = "expense"
            GROUP BY c.id, c.name, c.color, c.icon, c.is_essential
            HAVING total_spent > 0
            ORDER BY total_spent DESC
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Income breakdown by category.
     */
    public function getCategoryIncomeBreakdown(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.name, c.color, c.icon,
                   COALESCE(SUM(t.amount), 0) as total_income,
                   COUNT(t.id) as transaction_count
            FROM categories c
            LEFT JOIN transactions t ON t.category_id = c.id
                                     AND t.user_id = :uid
                                     AND t.type = "income"
                                     AND t.transaction_date BETWEEN :start AND :end
            WHERE (c.user_id = :uid OR c.user_id IS NULL)
              AND c.type = "income"
            GROUP BY c.id, c.name, c.color, c.icon
            HAVING total_income > 0
            ORDER BY total_income DESC
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Monthly trend over the last 6 months (Income vs Expense).
     */
    public function getSixMonthTrend(): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDt = new DateTime("-$i months");
            $start = $monthDt->format('Y-m-01');
            $end = $monthDt->format('Y-m-t');
            $monthLabel = $monthDt->format('M Y');

            $income = $this->getTotalIncome($start, $end);
            $expense = $this->getTotalExpenses($start, $end);
            $savings = $this->getActualSavingsContributions($start, $end);

            $trend[] = [
                'month'    => $monthLabel,
                'start'    => $start,
                'end'      => $end,
                'income'   => $income,
                'expenses' => $expense,
                'savings'  => $savings,
                'net'      => round($income - $expense, 2),
            ];
        }
        return $trend;
    }

    /**
     * Daily spending distribution for the current month.
     */
    public function getDailySpendingDistribution(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT transaction_date, SUM(amount) as total
            FROM transactions
            WHERE user_id = :uid
              AND type = "expense"
              AND transaction_date BETWEEN :start AND :end
            GROUP BY transaction_date
            ORDER BY transaction_date ASC
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['2026-10-01' => 450.00, ...]
    }

    /**
     * Get recent transactions with category details.
     */
    public function getRecentTransactions(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = :uid
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT :lim
        ');
        $stmt->bindValue(':uid', $this->userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get upcoming bills with due status and payment state.
     */
    public function getUpcomingBillsList(int $limit = 5): array
    {
        $today = (new DateTime('today'))->format('Y-m-d');
        $stmt = $this->pdo->prepare('
            SELECT b.*, c.name as category_name, c.color as category_color, c.icon as category_icon,
                   (CASE 
                        WHEN b.next_due_date < :today THEN "overdue"
                        WHEN b.next_due_date = :today THEN "due_today"
                        WHEN b.next_due_date <= DATE_ADD(:today, INTERVAL 7 DAY) THEN "due_soon"
                        ELSE "upcoming"
                    END) as urgency_status
            FROM recurring_bills b
            JOIN categories c ON c.id = b.category_id
            WHERE b.user_id = :uid
              AND b.status = "active"
              AND NOT EXISTS (
                  SELECT 1 FROM bill_payments p
                  WHERE p.bill_id = b.id AND p.due_date = b.next_due_date
              )
            ORDER BY b.next_due_date ASC
            LIMIT :lim
        ');
        $stmt->bindValue(':uid', $this->userId, PDO::PARAM_INT);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
