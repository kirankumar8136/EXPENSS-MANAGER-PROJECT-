<?php
/**
 * SmartSpend "Can I Afford This?" Purchase Calculator (Dedicated Full View)
 */

declare(strict_types=1);

$pageTitle = '"Can I Afford This?" Calculator';
$activePage = 'calculator';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/services/FinancialCalculations.php';
require_once __DIR__ . '/services/BudgetPlanner.php';

$pdo = getDbConnection();
$calc = new FinancialCalculations($pdo, $userId);
$planner = new BudgetPlanner($pdo, $userId);

$now = new DateTime('now');
$startDate = $now->format('Y-m-01');
$endDate = $now->format('Y-m-t');

$capacity = $planner->getMonthlyCapacityPlan($startDate, $endDate);
$accountBalance = $calc->getActualAccountBalance();
$upcomingBills = $calc->getUpcomingBillsTotal($now->format('Y-m-d'), $endDate);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Financial Context Banner -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Bank & Cash Reserve</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--text-primary); margin-top: 4px;">
            <?= formatCurrency($accountBalance, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Current liquid funds</div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Unpaid Bills Reserved</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--warning); margin-top: 4px;">
            <?= formatCurrency($upcomingBills, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Must not be spent on luxuries</div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Remaining Flexible Budget</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: #38bdf8; margin-top: 4px;">
            <?= formatCurrency($capacity['remaining_flexible'], $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Safe Discretionary Allowance</div>
    </div>
</div>

<div class="card" style="max-width: 760px; margin: 0 auto 32px;">
    <div class="card-header">
        <div>
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="help-circle" style="color: var(--primary);"></i>
                Evaluate a Potential Purchase
            </h3>
            <div class="card-subtitle">
                Before you buy, test whether the purchase will drain your flexible spending, endanger upcoming bills, or delay your savings targets.
            </div>
        </div>
    </div>

    <form id="affordabilityCalculatorForm">
        <?= csrfField() ?>
        <div class="form-group">
            <label class="form-label">What do you want to purchase?</label>
            <input type="text" name="purchase_name" class="form-control" placeholder="e.g. Sony Wireless Headphones, Ergonomic Chair, Weekend Getaway" required autofocus>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div class="form-group">
                <label class="form-label">Purchase Price (<?= $currency ?>)</label>
                <input type="number" step="0.01" min="1" name="price" class="form-control" placeholder="12999.00" required>
            </div>

            <div class="form-group">
                <label class="form-label">Planned Purchase Date</label>
                <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">How essential is this purchase?</label>
            <select name="is_essential" class="form-select">
                <option value="0" selected>Optional / Lifestyle (Discretionary shopping, leisure)</option>
                <option value="1">Essential / Urgent (Work equipment, medical, appliance repair)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
            <i data-lucide="calculator" style="width: 18px; height: 18px;"></i>
            Run Affordability Evaluation
        </button>
    </form>

    <div id="affordabilityResults" style="display: none;"></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
