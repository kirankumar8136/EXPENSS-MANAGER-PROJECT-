<?php
/**
 * Smart Salary-Based Money Management & Budget Engine
 * Provides personalized budgeting, flexible spending calculation,
 * daily allowances, and category spending limits without double-counting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/FinancialCalculations.php';

class BudgetPlanner
{
    private PDO $pdo;
    private int $userId;
    private array $settings;
    private FinancialCalculations $calc;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->calc = new FinancialCalculations($pdo, $userId);

        $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $this->settings = $stmt->fetch() ?: [
            'monthly_income'         => 0.00,
            'income_source_type'     => 'salaried',
            'income_frequency'       => 'monthly',
            'salary_day'             => 1,
            'is_income_fixed'        => 1,
            'monthly_savings_target' => 0.00,
            'savings_style'          => 'balanced',
            'budget_period_type'     => 'calendar_month',
        ];
    }

    /**
     * Compute full monthly financial capacity and flexible spending breakdown.
     */
    public function getMonthlyCapacityPlan(string $startDate, string $endDate): array
    {
        $expectedIncome = (float) ($this->settings['monthly_income'] ?? 0.0);
        $actualIncome = $this->calc->getTotalIncome($startDate, $endDate);
        
        // Effective income base: use expected income if configured, or actual income received if greater
        $effectiveIncome = $expectedIncome > 0 ? $expectedIncome : $actualIncome;

        // 1. Identify Essential Costs from category budgets and actual essential recurring bills
        $essentialCategories = $this->getEssentialCategoryIds();
        
        // Essential Budgeted or Spent
        $essentialBudgetTotal = $this->getEssentialBudgetTotal();
        $essentialSpent = $this->getEssentialSpentTotal($startDate, $endDate);

        // 2. Unpaid Recurring Bills due in this period
        // Exclude bills that belong to essential categories already budgeted to avoid double counting!
        $unpaidBillsBreakdown = $this->getUnpaidBillsExcludingBudgetedEssentials($startDate, $endDate);
        $unpaidSeparateBills = $unpaidBillsBreakdown['total'];

        // 3. Planned Savings Target
        $plannedSavings = (float) ($this->settings['monthly_savings_target'] ?? 0.0);
        if ($plannedSavings <= 0) {
            // Auto-calculate suggested savings based on savings style
            $style = $this->settings['savings_style'] ?? 'balanced';
            $rate = ($style === 'conservative') ? 0.10 : (($style === 'ambitious') ? 0.30 : 0.20);
            $plannedSavings = round($effectiveIncome * $rate, 2);
        }

        // 4. Actual discretionary spending already occurred
        $discretionarySpent = $this->getDiscretionarySpentTotal($startDate, $endDate);

        // 5. Projected Flexible Spending Formula:
        // Projected Flexible Spending = Expected Income - Essential Costs - Unpaid Separate Bills - Planned Savings
        $projectedFlexible = max(0.0, $effectiveIncome - $essentialBudgetTotal - $unpaidSeparateBills - $plannedSavings);

        // Remaining Flexible Spending for rest of month
        $remainingFlexible = max(0.0, $projectedFlexible - $discretionarySpent);

        // 6. Days remaining in current period
        $today = new DateTime('today');
        $endDt = new DateTime($endDate);
        $remainingDays = max(1, (int) $today->diff($endDt)->format('%r%a') + 1);

        // Daily suggested discretionary spending
        $dailyDiscretionary = round($remainingFlexible / $remainingDays, 2);

        // Overall budget usage
        $totalBudgeted = $this->getTotalBudgeted();
        $totalSpent = $this->calc->getTotalExpenses($startDate, $endDate);
        $budgetUsagePercent = getSafePercent($totalSpent, $totalBudgeted > 0 ? $totalBudgeted : $effectiveIncome);

        return [
            'expected_income'          => $expectedIncome,
            'actual_income'            => $actualIncome,
            'effective_income'         => $effectiveIncome,
            'essential_budget_total'   => $essentialBudgetTotal,
            'essential_spent_total'    => $essentialSpent,
            'unpaid_separate_bills'    => $unpaidSeparateBills,
            'unpaid_bills_list'        => $unpaidBillsBreakdown['bills'],
            'planned_savings_target'   => $plannedSavings,
            'projected_flexible_total' => $projectedFlexible,
            'discretionary_spent'      => $discretionarySpent,
            'remaining_flexible'       => $remainingFlexible,
            'remaining_days'           => $remainingDays,
            'daily_discretionary'      => $dailyDiscretionary,
            'total_budgeted'           => $totalBudgeted,
            'total_spent'              => $totalSpent,
            'budget_usage_percent'     => $budgetUsagePercent,
            'is_income_configured'     => ($expectedIncome > 0 || $actualIncome > 0),
        ];
    }

    /**
     * Get user's configured budgets joined with actual spending and categories.
     */
    public function getCategoryBudgetsWithActuals(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id as category_id, c.name, c.color, c.icon, c.is_essential,
                   COALESCE(b.monthly_amount, 0) as budget_amount,
                   COALESCE(SUM(t.amount), 0) as spent_amount
            FROM categories c
            LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = :uid
            LEFT JOIN transactions t ON t.category_id = c.id
                                     AND t.user_id = :uid
                                     AND t.type = "expense"
                                     AND t.transaction_date BETWEEN :start AND :end
            WHERE (c.user_id = :uid OR c.user_id IS NULL)
              AND c.type = "expense"
            GROUP BY c.id, c.name, c.color, c.icon, c.is_essential, b.monthly_amount
            ORDER BY c.is_essential DESC, budget_amount DESC, spent_amount DESC
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $budget = (float) $row['budget_amount'];
            $spent = (float) $row['spent_amount'];
            $row['remaining'] = round($budget - $spent, 2);
            $row['percent_used'] = getSafePercent($spent, $budget);

            if ($budget <= 0) {
                $row['status'] = $spent > 0 ? 'unbudgeted' : 'not_set';
                $row['status_badge'] = 'badge-neutral';
            } elseif ($spent > $budget) {
                $row['status'] = 'exceeded';
                $row['status_badge'] = 'badge-danger';
            } elseif ($row['percent_used'] >= 85) {
                $row['status'] = 'warning';
                $row['status_badge'] = 'badge-warning';
            } else {
                $row['status'] = 'healthy';
                $row['status_badge'] = 'badge-success';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Generate recommended budget allocations based on salary and living essentials.
     */
    public function generateRecommendedBudgets(float $monthlyIncome): array
    {
        if ($monthlyIncome <= 0) {
            $monthlyIncome = (float) ($this->settings['monthly_income'] ?? 35000.0);
        }

        // Standard recommended breakdown ratios based on sound financial planning (50/30/20 adapted)
        $rules = [
            'Housing & Rent'          => 0.28,
            'Food & Groceries'        => 0.18,
            'Electricity & Utilities' => 0.06,
            'Transportation'          => 0.08,
            'Healthcare & Medical'    => 0.05,
            'Entertainment & Dining'  => 0.08,
            'Shopping & Lifestyle'    => 0.07,
            'Subscriptions & Media'   => 0.03,
            'Personal Care'           => 0.04,
            'Other Expense'           => 0.03,
        ];

        // Fetch category IDs
        $stmt = $this->pdo->prepare('
            SELECT id, name FROM categories 
            WHERE (user_id = :uid OR user_id IS NULL) AND type = "expense"
        ');
        $stmt->execute(['uid' => $this->userId]);
        $categories = $stmt->fetchAll();

        $recommendations = [];
        foreach ($categories as $cat) {
            $ratio = $rules[$cat['name']] ?? 0.02;
            $recommendedAmount = round($monthlyIncome * $ratio, -1); // Round to nearest 10
            $recommendations[] = [
                'category_id'        => (int) $cat['id'],
                'category_name'      => $cat['name'],
                'ratio_percent'      => $ratio * 100,
                'recommended_amount' => $recommendedAmount,
            ];
        }

        return $recommendations;
    }

    /**
     * Evaluate "Can I Afford This?" purchase calculator.
     * Evaluates affordability against flexible spending, upcoming unpaid bills,
     * and monthly savings goals, and returns actionable alternative strategies.
     */
    public function evaluatePurchaseAffordability(
        string $purchaseName,
        float $price,
        string $purchaseDate,
        bool $isEssential = false
    ): array {
        $now = new DateTime('now');
        $startOfMonth = $now->format('Y-m-01');
        $endOfMonth = $now->format('Y-m-t');

        $capacity = $this->getMonthlyCapacityPlan($startOfMonth, $endOfMonth);
        $accountBalance = $this->calc->getActualAccountBalance();
        $upcomingBills = $this->calc->getUpcomingBillsTotal($now->format('Y-m-d'), $endOfMonth);
        $overdueBills = $this->calc->getOverdueBillsTotal();
        $totalUnpaidBills = $upcomingBills + $overdueBills;

        $remainingFlexible = $capacity['remaining_flexible'];
        $plannedSavings = $capacity['planned_savings_target'];

        // Determine affordability status
        // Scenario 1: Unaffordable / Dangerous
        // Purchase exceeds account balance OR (purchase + upcoming bills) exceeds account balance
        $isDefiniteDeficit = ($price > $accountBalance && $accountBalance > 0);
        $violatesBillCover = ($accountBalance - $price < $totalUnpaidBills && $accountBalance > 0);
        $violatesFlexible = ($price > $remainingFlexible);

        $status = 'affordable'; // 'affordable', 'tight', 'risky'
        $verdict = '';
        $reasons = [];
        $impacts = [];
        $alternatives = [];

        if ($isDefiniteDeficit || $violatesBillCover) {
            $status = 'risky';
            $verdict = "Not Recommended Right Now: This purchase would directly endanger your ability to pay upcoming bills or result in a negative cash balance.";
            if ($violatesBillCover) {
                $reasons[] = "You have " . formatCurrency($totalUnpaidBills, '₹') . " in upcoming/overdue bills this month. After spending " . formatCurrency($price, '₹') . ", your cash reserves will be insufficient to clear these bills.";
            }
            if ($violatesFlexible) {
                $shortfall = $price - $remainingFlexible;
                $reasons[] = "This purchase exceeds your remaining flexible spending allowance of " . formatCurrency($remainingFlexible, '₹') . " by " . formatCurrency($shortfall, '₹') . ".";
            }
        } elseif ($violatesFlexible) {
            $status = 'tight';
            $dipIntoSavings = $price - $remainingFlexible;
            $verdict = "Affordable with Compromise: You have sufficient cash in the bank, but this purchase exceeds your monthly discretionary allowance and will reduce your planned savings.";
            $reasons[] = "Buying this now exceeds your remaining monthly flexible budget of " . formatCurrency($remainingFlexible, '₹') . ".";
            $reasons[] = "To proceed without overdraft, you would need to draw " . formatCurrency($dipIntoSavings, '₹') . " from your planned monthly savings target of " . formatCurrency($plannedSavings, '₹') . ".";
        } else {
            $status = 'affordable';
            $postPurchaseFlexible = $remainingFlexible - $price;
            $verdict = "Comfortably Affordable! You have sufficient cash and flexible spending allowance to make this purchase without harming your bills or savings target.";
            $reasons[] = "Your remaining flexible budget after this purchase will still be approximately " . formatCurrency($postPurchaseFlexible, '₹') . ".";
            $reasons[] = "All " . formatCurrency($totalUnpaidBills, '₹') . " in upcoming bills remain 100% funded.";
        }

        // Daily impact
        $remainingDays = $capacity['remaining_days'];
        $oldDaily = $capacity['daily_discretionary'];
        $newFlexible = max(0.0, $remainingFlexible - $price);
        $newDaily = round($newFlexible / $remainingDays, 2);

        $impacts = [
            'purchase_price'              => $price,
            'current_remaining_flexible'  => $remainingFlexible,
            'projected_flexible_after'    => $newFlexible,
            'current_daily_allowance'     => $oldDaily,
            'new_daily_allowance'         => $newDaily,
            'daily_allowance_reduction'   => max(0.0, round($oldDaily - $newDaily, 2)),
            'unpaid_bills_covered'        => ($accountBalance - $price >= $totalUnpaidBills),
            'savings_target_impact'       => $violatesFlexible ? min($price - $remainingFlexible, $plannedSavings) : 0.0,
        ];

        // Actionable Alternatives
        if ($status !== 'affordable') {
            // Alternative 1: Spread across 3 months
            $threeMonthSave = round($price / 3, 2);
            $alternatives[] = [
                'type'        => 'plan_ahead',
                'title'       => 'Create a 3-Month Savings Plan',
                'description' => "Instead of buying immediately, save " . formatCurrency($threeMonthSave, '₹') . " per month for 3 months to purchase it debt-free.",
            ];

            // Alternative 2: Wait until next salary date
            $salaryDay = (int) ($this->settings['salary_day'] ?? 1);
            $alternatives[] = [
                'type'        => 'wait',
                'title'       => 'Postpone Until Next Salary Credit',
                'description' => "Wait until your next income deposit (around day {$salaryDay} of next month) when your flexible spending budget resets.",
            ];

            // Alternative 3: Reduce non-essential discretionary categories
            $alternatives[] = [
                'type'        => 'cut_spending',
                'title'       => 'Reduce Discretionary Spending',
                'description' => "Pause dining out, entertainment, and shopping for the rest of this month to free up extra cash flow.",
            ];
        }

        return [
            'purchase_name'  => $purchaseName,
            'price'          => $price,
            'is_essential'   => $isEssential,
            'status'         => $status, // affordable, tight, risky
            'verdict'        => $verdict,
            'reasons'        => $reasons,
            'impacts'        => $impacts,
            'alternatives'   => $alternatives,
            'disclaimer'     => 'Note: This calculation is a financial projection based on your recorded cash flow, scheduled bills, and configured budgets. It does not constitute formal financial advice.',
        ];
    }

    /**
     * Save/update a category budget amount.
     */
    public function setCategoryBudget(int $categoryId, float $amount): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO budgets (user_id, category_id, monthly_amount)
            VALUES (:uid, :cid, :amt)
            ON DUPLICATE KEY UPDATE monthly_amount = :amt2, updated_at = NOW()
        ');
        return $stmt->execute([
            'uid'  => $this->userId,
            'cid'  => $categoryId,
            'amt'  => $amount,
            'amt2' => $amount,
        ]);
    }

    // --- Private Helper Methods ---

    private function getEssentialCategoryIds(): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id FROM categories 
            WHERE (user_id = :uid OR user_id IS NULL) AND type = "expense" AND is_essential = 1
        ');
        $stmt->execute(['uid' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function getEssentialBudgetTotal(): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(b.monthly_amount), 0)
            FROM budgets b
            JOIN categories c ON c.id = b.category_id
            WHERE b.user_id = :uid AND c.is_essential = 1
        ');
        $stmt->execute(['uid' => $this->userId]);
        return (float) $stmt->fetchColumn();
    }

    private function getEssentialSpentTotal(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(t.amount), 0)
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = :uid
              AND t.type = "expense"
              AND c.is_essential = 1
              AND t.transaction_date BETWEEN :start AND :end
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    private function getDiscretionarySpentTotal(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(t.amount), 0)
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = :uid
              AND t.type = "expense"
              AND c.is_essential = 0
              AND t.transaction_date BETWEEN :start AND :end
        ');
        $stmt->execute(['uid' => $this->userId, 'start' => $startDate, 'end' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    private function getTotalBudgeted(): float
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(monthly_amount), 0) FROM budgets WHERE user_id = :uid');
        $stmt->execute(['uid' => $this->userId]);
        return (float) $stmt->fetchColumn();
    }

    private function getUnpaidBillsExcludingBudgetedEssentials(string $startDate, string $endDate): array
    {
        $today = (new DateTime('today'))->format('Y-m-d');
        // Select active bills not paid yet that are NOT in an essential category that already has a budget
        $stmt = $this->pdo->prepare('
            SELECT b.*, c.name as category_name
            FROM recurring_bills b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN budgets bg ON bg.category_id = c.id AND bg.user_id = :uid AND bg.monthly_amount > 0
            WHERE b.user_id = :uid
              AND b.status = "active"
              AND b.next_due_date >= :today
              AND b.next_due_date <= :end_date
              AND bg.id IS NULL -- Exclude if category already has an explicit budget allocation
              AND NOT EXISTS (
                  SELECT 1 FROM bill_payments p
                  WHERE p.bill_id = b.id AND p.due_date = b.next_due_date
              )
        ');
        $stmt->execute(['uid' => $this->userId, 'today' => $today, 'end_date' => $endDate]);
        $bills = $stmt->fetchAll();

        $total = 0.0;
        foreach ($bills as $b) {
            $total += (float) $b['amount'];
        }

        return ['bills' => $bills, 'total' => $total];
    }
}
