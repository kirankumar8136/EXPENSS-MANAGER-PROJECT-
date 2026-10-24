<?php
/**
 * SmartSpend Reports & Analytics
 */

declare(strict_types=1);

$pageTitle = 'Financial Reports & Analytics';
$activePage = 'reports';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/FinancialCalculations.php';
require_once __DIR__ . '/services/BudgetPlanner.php';

$pdo = getDbConnection();
$calc = new FinancialCalculations($pdo, $userId);
$planner = new BudgetPlanner($pdo, $userId);

// Selected Timeframe Filter
$timeframe = $_GET['timeframe'] ?? 'this_month';
$now = new DateTime('now');

switch ($timeframe) {
    case 'last_month':
        $startDate = (new DateTime('first day of last month'))->format('Y-m-d');
        $endDate = (new DateTime('last day of last month'))->format('Y-m-d');
        $label = (new DateTime('first day of last month'))->format('F Y');
        break;

    case 'this_quarter':
        $currentMonth = (int) $now->format('n');
        $quarter = (int) ceil($currentMonth / 3);
        $quarterStartMonth = ($quarter - 1) * 3 + 1;
        $startDate = (new DateTime(sprintf('%s-%02d-01', $now->format('Y'), $quarterStartMonth)))->format('Y-m-d');
        $endDate = (new DateTime(sprintf('%s-%02d-01', $now->format('Y'), $quarterStartMonth + 2)))->format('Y-m-t');
        $label = "Q{$quarter} " . $now->format('Y');
        break;

    case 'this_year':
        $startDate = $now->format('Y-01-01');
        $endDate = $now->format('Y-12-31');
        $label = 'Year ' . $now->format('Y');
        break;

    case 'custom':
        $startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : $now->format('Y-m-01');
        $endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : $now->format('Y-m-t');
        $label = formatDate($startDate) . ' – ' . formatDate($endDate);
        break;

    case 'this_month':
    default:
        $startDate = $now->format('Y-m-01');
        $endDate = $now->format('Y-m-t');
        $label = $now->format('F Y');
        break;
}

// Aggregated totals
$totalIncome = $calc->getTotalIncome($startDate, $endDate);
$totalExpenses = $calc->getTotalExpenses($startDate, $endDate);
$netSavings = $totalIncome - $totalExpenses;
$savingsDeposits = $calc->getActualSavingsContributions($startDate, $endDate);

// Category breakdown
$categoryExpenses = $calc->getCategoryExpenseBreakdown($startDate, $endDate);
$categoryIncomes = $calc->getCategoryIncomeBreakdown($startDate, $endDate);

// Payment method breakdown
$stmt = $pdo->prepare('
    SELECT payment_method, COUNT(id) as count, SUM(amount) as total
    FROM transactions
    WHERE user_id = :uid AND type = "expense" AND transaction_date BETWEEN :start AND :end
    GROUP BY payment_method
    ORDER BY total DESC
');
$stmt->execute(['uid' => $userId, 'start' => $startDate, 'end' => $endDate]);
$methodBreakdown = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Report Timeframe Header -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div class="period-selector">
            <a href="reports.php?timeframe=this_month" class="period-btn <?= $timeframe === 'this_month' ? 'active' : '' ?>">This Month</a>
            <a href="reports.php?timeframe=last_month" class="period-btn <?= $timeframe === 'last_month' ? 'active' : '' ?>">Last Month</a>
            <a href="reports.php?timeframe=this_quarter" class="period-btn <?= $timeframe === 'this_quarter' ? 'active' : '' ?>">This Quarter</a>
            <a href="reports.php?timeframe=this_year" class="period-btn <?= $timeframe === 'this_year' ? 'active' : '' ?>">Full Year</a>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="actions/transaction_actions.php?action=export_csv&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-outline btn-sm">
                <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export Transactions CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i data-lucide="printer" style="width: 14px; height: 14px;"></i> Print Report
            </button>
        </div>
    </div>
</div>

<!-- Financial Performance KPI Grid -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Total Income Received</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--success); margin-top: 4px;">
            <?= formatCurrency($totalIncome, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?= sanitize($label) ?></div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Total Outflow (Expenses)</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--danger); margin-top: 4px;">
            <?= formatCurrency($totalExpenses, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?= sanitize($label) ?></div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Net Cash Savings</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: <?= $netSavings >= 0 ? '#38bdf8' : 'var(--danger)' ?>; margin-top: 4px;">
            <?= formatCurrency($netSavings, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
            Savings Rate: <?= getSafePercent($netSavings, $totalIncome) ?>%
        </div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Goals Contributed</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">
            <?= formatCurrency($savingsDeposits, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Direct deposits to goals</div>
    </div>
</div>

<!-- Two-Column Breakdown: Categories & Payment Methods -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Category Wise Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="pie-chart" style="color: var(--primary);"></i> Expense Category Distribution</h3>
        </div>

        <?php if (empty($categoryExpenses)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
                <div class="empty-state-title">No Expenses in this Timeframe</div>
                <div class="empty-state-text">No expense transactions recorded between <?= formatDate($startDate) ?> and <?= formatDate($endDate) ?>.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Transactions</th>
                            <th style="text-align: right;">Amount Spent</th>
                            <th style="text-align: right;">Share of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryExpenses as $c): 
                            $share = getSafePercent($c['total_spent'], $totalExpenses);
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--text-primary);"><?= sanitize($c['name']) ?></strong>
                                </td>
                                <td style="color: var(--text-muted); font-size: 0.84rem;"><?= $c['transaction_count'] ?> txns</td>
                                <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                    <?= formatCurrency($c['total_spent'], $currency) ?>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: var(--text-secondary);">
                                    <?= $share ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Methods Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="credit-card" style="color: #06b6d4;"></i> Payment Modes</h3>
        </div>

        <?php if (empty($methodBreakdown)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="credit-card"></i></div>
                <div class="empty-state-title">No Activity</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($methodBreakdown as $m): 
                    $pct = getSafePercent($m['total'], $totalExpenses);
                ?>
                    <div style="padding: 12px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.86rem; margin-bottom: 4px;">
                            <strong style="color: var(--text-primary);"><?= getPaymentMethodLabel($m['payment_method']) ?></strong>
                            <span style="font-weight: 700; color: var(--text-primary);"><?= formatCurrency($m['total'], $currency) ?></span>
                        </div>
                        <div class="progress-container" style="height: 6px; margin: 4px 0;">
                            <div class="progress-bar primary" style="width: <?= $pct ?>%;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted);">
                            <span><?= $m['count'] ?> transactions</span>
                            <span><?= $pct ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
