<?php
/**
 * Financial Insights and Spending Analysis Engine
 * Generates transparent, actionable, rule-based financial advice
 * using apples-to-apples equivalent period comparisons.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/FinancialCalculations.php';
require_once __DIR__ . '/BudgetPlanner.php';

class FinancialInsights
{
    private PDO $pdo;
    private int $userId;
    private FinancialCalculations $calc;
    private BudgetPlanner $planner;
    private string $currency;

    public function __construct(PDO $pdo, int $userId, string $currency = '₹')
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->currency = $currency;
        $this->calc = new FinancialCalculations($pdo, $userId);
        $this->planner = new BudgetPlanner($pdo, $userId);
    }

    /**
     * Generate all actionable financial insights for the current period.
     *
     * @return array List of insight objects
     */
    public function generateInsights(): array
    {
        $insights = [];
        $now = new DateTime('now');
        $dayOfMonth = (int) $now->format('j');
        $daysInMonth = (int) $now->format('t');

        // Current period (Month start to today)
        $curStart = $now->format('Y-m-01');
        $curEnd = $now->format('Y-m-d');
        $fullMonthEnd = $now->format('Y-m-t');

        // Equivalent previous month period (e.g. 1st to 24th of last month)
        $prevMonthDt = new DateTime('first day of last month');
        $prevYear = $prevMonthDt->format('Y');
        $prevMonth = $prevMonthDt->format('m');
        $maxDaysInPrevMonth = (int) $prevMonthDt->format('t');
        $equivPrevDay = min($dayOfMonth, $maxDaysInPrevMonth);
        $prevStart = sprintf('%s-%s-01', $prevYear, $prevMonth);
        $prevEnd = sprintf('%s-%s-%02d', $prevYear, $prevMonth, $equivPrevDay);

        // 1. Capacity & Flexible Budget Analysis
        $capacity = $this->planner->getMonthlyCapacityPlan($curStart, $fullMonthEnd);
        $curExpenses = $this->calc->getTotalExpenses($curStart, $curEnd);
        $prevExpensesEquiv = $this->calc->getTotalExpenses($prevStart, $prevEnd);

        // A. Discretionary Burn Rate Alert
        if ($capacity['is_income_configured'] && $capacity['remaining_flexible'] <= 0 && $capacity['remaining_days'] > 3) {
            $insights[] = [
                'type'        => 'danger',
                'icon'        => 'alert-triangle',
                'title'       => 'Flexible Spending Allowance Depleted',
                'description' => "You have used 100% of your projected flexible spending budget with {$capacity['remaining_days']} days remaining in the month. Any additional non-essential purchases will cut into your savings or upcoming bill reserves.",
                'action_text' => 'Review discretionary spending and pause non-essential purchases.',
                'action_url'  => 'smart_budget.php',
            ];
        } elseif ($capacity['daily_discretionary'] > 0) {
            $insights[] = [
                'type'        => 'info',
                'icon'        => 'compass',
                'title'       => 'Safe Daily Discretionary Allowance',
                'description' => "Based on your remaining flexible budget of " . formatCurrency($capacity['remaining_flexible'], $this->currency) . ", you can safely spend up to " . formatCurrency($capacity['daily_discretionary'], $this->currency) . " per day for the remaining {$capacity['remaining_days']} days without disturbing upcoming bills.",
                'action_text' => 'Stay within ' . formatCurrency($capacity['daily_discretionary'], $this->currency) . '/day to meet your monthly savings goal.',
                'action_url'  => 'smart_budget.php',
            ];
        }

        // 2. Category Spending Surges (Apples-to-Apples Comparison)
        $categorySurges = $this->detectCategorySurges($curStart, $curEnd, $prevStart, $prevEnd);
        foreach ($categorySurges as $surge) {
            $insights[] = $surge;
        }

        // 3. Category Budget Overruns
        $categoryBudgets = $this->planner->getCategoryBudgetsWithActuals($curStart, $fullMonthEnd);
        foreach ($categoryBudgets as $cb) {
            if ($cb['budget_amount'] > 0) {
                if ($cb['spent_amount'] > $cb['budget_amount']) {
                    $overAmount = $cb['spent_amount'] - $cb['budget_amount'];
                    $insights[] = [
                        'type'        => 'danger',
                        'icon'        => 'trending-up',
                        'title'       => "Budget Exceeded: {$cb['name']}",
                        'description' => "You have spent " . formatCurrency($cb['spent_amount'], $this->currency) . " in {$cb['name']}, exceeding your budget of " . formatCurrency($cb['budget_amount'], $this->currency) . " by " . formatCurrency($overAmount, $this->currency) . " (" . $cb['percent_used'] . "% used).",
                        'action_text' => 'Adjust category budget or curb additional spending in this category.',
                        'action_url'  => 'smart_budget.php',
                    ];
                } elseif ($cb['percent_used'] >= 85) {
                    $insights[] = [
                        'type'        => 'warning',
                        'icon'        => 'alert-circle',
                        'title'       => "Budget Warning: {$cb['name']}",
                        'description' => "You have consumed {$cb['percent_used']}% of your monthly {$cb['name']} budget with " . formatCurrency($cb['remaining'], $this->currency) . " remaining.",
                        'action_text' => 'Monitor upcoming transactions in ' . $cb['name'] . '.',
                        'action_url'  => 'transactions.php?category_id=' . $cb['category_id'],
                    ];
                }
            }
        }

        // 4. Subscription & Recurring Commitments Burden
        $subscriptionInsight = $this->analyzeSubscriptionLoad($capacity['effective_income']);
        if ($subscriptionInsight) {
            $insights[] = $subscriptionInsight;
        }

        // 5. Savings Goal Milestone or Lag
        $savingsInsight = $this->analyzeSavingsPace($curStart, $fullMonthEnd, $capacity['planned_savings_target'], $dayOfMonth, $daysInMonth);
        if ($savingsInsight) {
            $insights[] = $savingsInsight;
        }

        // 6. Overall Month-over-Month Expense Comparison
        if ($prevExpensesEquiv > 0) {
            $diff = $curExpenses - $prevExpensesEquiv;
            $percentDiff = round(($diff / $prevExpensesEquiv) * 100, 1);
            if ($diff < 0) {
                $insights[] = [
                    'type'        => 'success',
                    'icon'        => 'check-circle-2',
                    'title'       => 'Spending Reduction Win',
                    'description' => "Great job! Your spending in the first {$dayOfMonth} days of this month is " . formatCurrency(abs($diff), $this->currency) . " lower (" . abs($percentDiff) . "% less) than the exact same period last month.",
                    'action_text' => 'Keep up the momentum to bank higher surplus savings.',
                    'action_url'  => 'reports.php',
                ];
            } elseif ($percentDiff >= 20) {
                $insights[] = [
                    'type'        => 'warning',
                    'icon'        => 'arrow-up-right',
                    'title'       => 'Pacing Higher Than Last Month',
                    'description' => "Your expenses are up by {$percentDiff}% (" . formatCurrency($diff, $this->currency) . " higher) compared to the first {$dayOfMonth} days of last month.",
                    'action_text' => 'Check your transaction history to see where the increase occurred.',
                    'action_url'  => 'transactions.php',
                ];
            }
        }

        return $insights;
    }

    /**
     * Detect categories where spending rose >= 20% compared to equivalent previous period.
     */
    private function detectCategorySurges(string $curStart, string $curEnd, string $prevStart, string $prevEnd): array
    {
        $surges = [];
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.name,
                   COALESCE(SUM(CASE WHEN t.transaction_date BETWEEN :cur_start AND :cur_end THEN t.amount ELSE 0 END), 0) as cur_total,
                   COALESCE(SUM(CASE WHEN t.transaction_date BETWEEN :prev_start AND :prev_end THEN t.amount ELSE 0 END), 0) as prev_total
            FROM categories c
            JOIN transactions t ON t.category_id = c.id AND t.user_id = :uid AND t.type = "expense"
            WHERE (t.transaction_date BETWEEN :cur_start AND :cur_end OR t.transaction_date BETWEEN :prev_start AND :prev_end)
            GROUP BY c.id, c.name
            HAVING cur_total > 500 AND prev_total > 0
        ');
        $stmt->execute([
            'uid'        => $this->userId,
            'cur_start'  => $curStart,
            'cur_end'    => $curEnd,
            'prev_start' => $prevStart,
            'prev_end'   => $prevEnd,
        ]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $cur = (float) $r['cur_total'];
            $prev = (float) $r['prev_total'];
            $diff = $cur - $prev;
            $percent = round(($diff / $prev) * 100, 1);

            if ($percent >= 25 && $diff >= 400) {
                $surges[] = [
                    'type'        => 'warning',
                    'icon'        => 'trending-up',
                    'title'       => "Surge in {$r['name']} Spending",
                    'description' => "Your {$r['name']} spending increased by {$percent}% (+" . formatCurrency($diff, $this->currency) . ") compared with the same period last month (" . formatCurrency($cur, $this->currency) . " vs " . formatCurrency($prev, $this->currency) . ").",
                    'action_text' => 'Review transactions in ' . $r['name'] . ' to identify recurring or unusual charges.',
                    'action_url'  => 'transactions.php?category_id=' . $r['id'],
                ];
            }
        }

        return $surges;
    }

    /**
     * Analyze recurring subscription spending relative to monthly income.
     */
    private function analyzeSubscriptionLoad(float $monthlyIncome): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(b.amount), 0) as total, COUNT(b.id) as count
            FROM recurring_bills b
            JOIN categories c ON c.id = b.category_id
            WHERE b.user_id = :uid
              AND b.status = "active"
              AND (c.name LIKE "%Subscription%" OR b.name LIKE "%Netflix%" OR b.name LIKE "%Spotify%" OR b.name LIKE "%Prime%" OR b.name LIKE "%Hotstar%" OR b.name LIKE "%Gym%")
        ');
        $stmt->execute(['uid' => $this->userId]);
        $subData = $stmt->fetch();

        $subTotal = (float) ($subData['total'] ?? 0.0);
        $subCount = (int) ($subData['count'] ?? 0);

        if ($subCount >= 2 && $subTotal > 0) {
            $pctOfIncome = $monthlyIncome > 0 ? round(($subTotal / $monthlyIncome) * 100, 1) : 0;
            return [
                'type'        => 'info',
                'icon'        => 'tv',
                'title'       => "Active Subscriptions ({$subCount} active)",
                'description' => "You have {$subCount} recurring subscription commitments totaling " . formatCurrency($subTotal, $this->currency) . " per month" . ($pctOfIncome > 0 ? " ({$pctOfIncome}% of monthly income)" : "") . ". Auditing unused streaming and digital memberships can free up immediate cash flow.",
                'action_text' => 'Audit subscriptions and recurring memberships.',
                'action_url'  => 'bills.php',
            ];
        }

        return null;
    }

    /**
     * Check if user is pacing well against their monthly savings goal.
     */
    private function analyzeSavingsPace(string $curStart, string $fullMonthEnd, float $monthlyTarget, int $dayOfMonth, int $daysInMonth): ?array
    {
        if ($monthlyTarget <= 0) {
            return null;
        }

        $actualSaved = $this->calc->getActualSavingsContributions($curStart, $fullMonthEnd);
        $expectedPaceAmount = round(($monthlyTarget / $daysInMonth) * $dayOfMonth, 2);

        if ($actualSaved >= $monthlyTarget) {
            return [
                'type'        => 'success',
                'icon'        => 'award',
                'title'       => 'Monthly Savings Goal Achieved!',
                'description' => "Congratulations! You have contributed " . formatCurrency($actualSaved, $this->currency) . " towards your savings goals, surpassing your monthly target of " . formatCurrency($monthlyTarget, $this->currency) . ".",
                'action_text' => 'View savings goals milestones and targets.',
                'action_url'  => 'savings_goals.php',
            ];
        } elseif ($actualSaved < ($expectedPaceAmount * 0.5) && $dayOfMonth > 15) {
            $shortfall = $monthlyTarget - $actualSaved;
            return [
                'type'        => 'warning',
                'icon'        => 'clock',
                'title'       => 'Savings Target Behind Schedule',
                'description' => "You have saved " . formatCurrency($actualSaved, $this->currency) . " of your " . formatCurrency($monthlyTarget, $this->currency) . " monthly target. With " . ($daysInMonth - $dayOfMonth) . " days remaining, you are " . formatCurrency($shortfall, $this->currency) . " away from your goal.",
                'action_text' => 'Deposit a contribution or review your flexible budget.',
                'action_url'  => 'savings_goals.php',
            ];
        }

        return null;
    }
}
