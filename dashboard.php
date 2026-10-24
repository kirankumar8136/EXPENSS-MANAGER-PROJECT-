<?php
/**
 * SmartSpend Master Financial Dashboard
 */

declare(strict_types=1);

$pageTitle = 'Financial Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

// Ensure onboarding is completed
if (empty($user['onboarding_completed'])) {
    redirect('onboarding.php');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/FinancialCalculations.php';
require_once __DIR__ . '/services/BudgetPlanner.php';
require_once __DIR__ . '/services/FinancialInsights.php';

$pdo = getDbConnection();
$calc = new FinancialCalculations($pdo, $userId);
$planner = new BudgetPlanner($pdo, $userId);
$insightsEngine = new FinancialInsights($pdo, $userId, $currency);

// 1. Resolve selected period
$periodParam = $_GET['period'] ?? 'current_month';
$customStart = $_GET['start_date'] ?? null;
$customEnd = $_GET['end_date'] ?? null;
$resolved = FinancialCalculations::resolvePeriod($periodParam, $customStart, $customEnd);
$startDate = $resolved['start_date'];
$endDate = $resolved['end_date'];

// 2. Compute 8 Core Financial Metrics
$totalIncome = $calc->getTotalIncome($startDate, $endDate);
$totalExpenses = $calc->getTotalExpenses($startDate, $endDate);
$actualBalance = $calc->getActualAccountBalance();
$actualSavings = $calc->getActualSavingsContributions($startDate, $endDate);
$upcomingBillsTotal = $calc->getUpcomingBillsTotal($startDate, $endDate);
$capacityPlan = $planner->getMonthlyCapacityPlan($startDate, $endDate);

$plannedSavingsTarget = $capacityPlan['planned_savings_target'];
$remainingFlexible = $capacityPlan['remaining_flexible'];
$budgetUsagePercent = $capacityPlan['budget_usage_percent'];

// 3. Fetch Chart & Widget Data
$categoryExpenses = $calc->getCategoryExpenseBreakdown($startDate, $endDate);
$sixMonthTrend = $calc->getSixMonthTrend();
$categoryBudgets = $planner->getCategoryBudgetsWithActuals($startDate, $endDate);
$recentTransactions = $calc->getRecentTransactions(6);
$upcomingBillsList = $calc->getUpcomingBillsList(5);
$activeInsights = $insightsEngine->generateInsights();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Dashboard Controls Header -->
<div class="dashboard-controls">
    <!-- Period Selector Switcher -->
    <div class="period-selector">
        <a href="dashboard.php?period=current_month" class="period-btn <?= $periodParam === 'current_month' ? 'active' : '' ?>">This Month</a>
        <a href="dashboard.php?period=last_month" class="period-btn <?= $periodParam === 'last_month' ? 'active' : '' ?>">Last Month</a>
        <a href="dashboard.php?period=last_30_days" class="period-btn <?= $periodParam === 'last_30_days' ? 'active' : '' ?>">Last 30 Days</a>
        <a href="dashboard.php?period=year_to_date" class="period-btn <?= $periodParam === 'year_to_date' ? 'active' : '' ?>">Year to Date</a>
    </div>

    <!-- Quick Action Buttons -->
    <div class="quick-actions-bar">
        <button class="btn btn-outline btn-sm" onclick="openModal('quickTransactionModal')">
            <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Transaction
        </button>
        <button class="btn btn-outline btn-sm" onclick="openModal('affordabilityCalculatorModal')">
            <i data-lucide="help-circle" style="width: 14px; height: 14px;"></i> Can I Afford This?
        </button>
        <a href="reports.php" class="btn btn-outline btn-sm">
            <i data-lucide="file-text" style="width: 14px; height: 14px;"></i> View Reports
        </a>
    </div>
</div>

<!-- =====================================================================
     A. 8 FINANCIAL SUMMARY CARDS GRID
     ===================================================================== -->
<div class="kpi-grid">
    <!-- 1. Total Income -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Total Income</span>
            <div class="kpi-icon income"><i data-lucide="trending-up" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($totalIncome, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Period: <?= sanitize($resolved['label']) ?></span>
        </div>
    </div>

    <!-- 2. Total Expenses -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Total Expenses</span>
            <div class="kpi-icon expense"><i data-lucide="trending-down" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($totalExpenses, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Net Flow: <?= formatCurrency($totalIncome - $totalExpenses, $currency) ?></span>
        </div>
    </div>

    <!-- 3. Actual Remaining Balance -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Account Balance</span>
            <div class="kpi-icon balance"><i data-lucide="wallet" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($actualBalance, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Recorded cash & bank reserve</span>
        </div>
    </div>

    <!-- 4. Planned Savings Target -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Savings Target</span>
            <div class="kpi-icon savings"><i data-lucide="target" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($plannedSavingsTarget, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Monthly wealth goal</span>
        </div>
    </div>

    <!-- 5. Actual Savings Contributions -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Actual Savings</span>
            <div class="kpi-icon savings"><i data-lucide="piggy-bank" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($actualSavings, $currency) ?></div>
        <div class="kpi-subtext">
            <span><?= getSafePercent($actualSavings, $plannedSavingsTarget) ?>% of target deposited</span>
        </div>
    </div>

    <!-- 6. Upcoming Bills Total -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Upcoming Bills</span>
            <div class="kpi-icon bills"><i data-lucide="calendar-alert" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($upcomingBillsTotal, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Unpaid commitments this cycle</span>
        </div>
    </div>

    <!-- 7. Remaining Flexible Spending Budget -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Flexible Budget</span>
            <div class="kpi-icon flexible"><i data-lucide="compass" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= formatCurrency($remainingFlexible, $currency) ?></div>
        <div class="kpi-subtext">
            <span>Safe Daily: <?= formatCurrency($capacityPlan['daily_discretionary'], $currency) ?>/day</span>
        </div>
    </div>

    <!-- 8. Budget Usage Percentage -->
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Budget Utilized</span>
            <div class="kpi-icon budget"><i data-lucide="pie-chart" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-value"><?= $budgetUsagePercent ?>%</div>
        <div class="kpi-subtext">
            <span><?= $budgetUsagePercent > 100 ? 'Exceeded Budget' : 'On Track' ?></span>
        </div>
    </div>
</div>

<!-- =====================================================================
     B. FINANCIAL CHARTS GRID
     ===================================================================== -->
<div class="charts-grid-main">
    <!-- Chart 1: 6-Month Income vs Expense Trend -->
    <div class="chart-card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="line-chart" style="color: var(--primary);"></i> Income & Expense Trend</h3>
                <div class="card-subtitle">Cash flow trajectory over the past 6 months</div>
            </div>
        </div>
        <div class="chart-card-body">
            <canvas id="expenseTrendCanvas"></canvas>
        </div>
    </div>

    <!-- Chart 2: Category Spending Doughnut -->
    <div class="chart-card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="pie-chart" style="color: #f59e0b;"></i> Category Breakdown</h3>
                <div class="card-subtitle">Where your money went in <?= sanitize($resolved['label']) ?></div>
            </div>
        </div>
        <div class="chart-card-body">
            <canvas id="categoryDoughnutCanvas"></canvas>
        </div>
    </div>
</div>

<div class="charts-grid-secondary">
    <!-- Chart 3: Income vs Expense Comparison -->
    <div class="chart-card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="bar-chart-2" style="color: var(--success);"></i> Period Cash Flow</h3>
                <div class="card-subtitle">Total Earned vs Total Spent</div>
            </div>
        </div>
        <div class="chart-card-body">
            <canvas id="incomeVsExpenseCanvas"></canvas>
        </div>
    </div>

    <!-- Chart 4: Budget vs Actual Comparison -->
    <div class="chart-card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i data-lucide="scale" style="color: #8b5cf6;"></i> Budget vs Actual Spending</h3>
                <div class="card-subtitle">Planned limit vs actual expense per category</div>
            </div>
            <a href="smart_budget.php" class="btn btn-outline btn-sm">Manage</a>
        </div>
        <div class="chart-card-body">
            <canvas id="budgetVsActualCanvas"></canvas>
        </div>
    </div>
</div>

<!-- =====================================================================
     C & D. RECENT TRANSACTIONS & UPCOMING BILLS WIDGETS
     ===================================================================== -->
<div class="dashboard-widgets-grid">
    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="clock" style="color: var(--primary);"></i> Recent Transactions</h3>
            <a href="transactions.php" class="btn btn-outline btn-sm">View All &rarr;</a>
        </div>

        <?php if (empty($recentTransactions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
                <div class="empty-state-title">No Transactions Found</div>
                <div class="empty-state-text">You haven't recorded any income or expenses yet.</div>
                <button class="btn btn-primary btn-sm" onclick="openModal('quickTransactionModal')">Record First Transaction</button>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($recentTransactions as $txn): ?>
                    <div class="txn-item">
                        <div class="txn-left">
                            <div class="txn-icon" style="background-color: <?= $txn['type'] === 'income' ? 'var(--success-light)' : 'var(--bg-surface)' ?>; color: <?= $txn['type'] === 'income' ? 'var(--success)' : ($txn['category_color'] ?: 'var(--text-secondary)') ?>;">
                                <i data-lucide="<?= $txn['type'] === 'income' ? 'arrow-down-left' : ($txn['category_icon'] ?: 'arrow-up-right') ?>" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <div class="txn-title"><?= sanitize($txn['description']) ?></div>
                                <div class="txn-meta">
                                    <span><?= sanitize($txn['category_name']) ?></span> • 
                                    <span><?= formatDate($txn['transaction_date'], 'd M Y') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="txn-right">
                            <div class="txn-amount <?= $txn['type'] ?>">
                                <?= $txn['type'] === 'income' ? '+' : '-' ?><?= formatCurrency($txn['amount'], $currency) ?>
                            </div>
                            <div class="txn-method-tag"><?= getPaymentMethodLabel($txn['payment_method']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Bills & Deadlines -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="calendar-clock" style="color: var(--warning);"></i> Upcoming Bills & Deadlines</h3>
            <a href="bills.php" class="btn btn-outline btn-sm">All Bills &rarr;</a>
        </div>

        <?php if (empty($upcomingBillsList)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="check-check"></i></div>
                <div class="empty-state-title">All Bills Paid!</div>
                <div class="empty-state-text">You have no upcoming or overdue unpaid bills for this period.</div>
                <a href="bills.php" class="btn btn-outline btn-sm">Manage Recurring Bills</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($upcomingBillsList as $b): 
                    $dueInfo = getDueStatus($b['next_due_date']);
                ?>
                    <div class="bill-widget-item">
                        <div class="bill-widget-left">
                            <div class="txn-icon" style="background: rgba(255,255,255,0.05); color: <?= $b['category_color'] ?: 'var(--primary)' ?>;">
                                <i data-lucide="<?= $b['category_icon'] ?: 'receipt' ?>" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <div class="bill-widget-name"><?= sanitize($b['name']) ?></div>
                                <div class="bill-widget-due <?= $dueInfo['class'] ?>"><?= $dueInfo['text'] ?></div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                                <?= formatCurrency($b['amount'], $currency) ?>
                            </div>
                            <button class="btn btn-success btn-sm" onclick="openPayBillModal(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['name'])) ?>', <?= (float)$b['amount'] ?>, '<?= $b['next_due_date'] ?>')">
                                Pay
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- =====================================================================
     E. ACTIONABLE FINANCIAL INSIGHTS FEED
     ===================================================================== -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="sparkles" style="color: var(--primary);"></i> Smart Financial Insights</h3>
        <a href="insights.php" class="btn btn-outline btn-sm">Deep Analysis &rarr;</a>
    </div>

    <?php if (empty($activeInsights)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="zap"></i></div>
            <div class="empty-state-title">Financial Health Looking Good</div>
            <div class="empty-state-text">As you record more transactions and bills, SmartSpend will detect category spending surges, budget warnings, and savings optimization opportunities.</div>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
            <?php foreach (array_slice($activeInsights, 0, 4) as $insight): ?>
                <div class="insight-feed-card <?= sanitize($insight['type']) ?>">
                    <div class="insight-feed-icon">
                        <i data-lucide="<?= sanitize($insight['icon']) ?>" style="width: 17px; height: 17px;"></i>
                    </div>
                    <div class="insight-feed-body">
                        <div class="insight-feed-title"><?= sanitize($insight['title']) ?></div>
                        <div class="insight-feed-desc"><?= sanitize($insight['description']) ?></div>
                        <?php if (!empty($insight['action_text'])): ?>
                            <a href="<?= sanitize($insight['action_url']) ?>" class="insight-feed-action" style="color: var(--primary);">
                                <span><?= sanitize($insight['action_text']) ?></span> &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Initialize Charts on Page Load -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const currency = '<?= $currency ?>';

    // 1. 6-Month Trend
    const trendData = <?= json_encode($sixMonthTrend) ?>;
    SmartSpendCharts.initExpenseTrend('expenseTrendCanvas', trendData, currency);

    // 2. Category Doughnut
    const catData = <?= json_encode($categoryExpenses) ?>;
    SmartSpendCharts.initCategoryDoughnut('categoryDoughnutCanvas', catData, currency);

    // 3. Income vs Expense
    SmartSpendCharts.initIncomeVsExpense('incomeVsExpenseCanvas', <?= (float)$totalIncome ?>, <?= (float)$totalExpenses ?>, currency);

    // 4. Budget vs Actual
    const budgetsData = <?= json_encode($categoryBudgets) ?>;
    SmartSpendCharts.initBudgetVsActual('budgetVsActualCanvas', budgetsData, currency);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
