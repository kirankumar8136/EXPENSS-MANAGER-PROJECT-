<?php
/**
 * Smart Salary-Based Money Management & Budget Engine
 */

declare(strict_types=1);

$pageTitle = 'Smart Budget Engine';
$activePage = 'smart_budget';

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
$planner = new BudgetPlanner($pdo, $userId);

$now = new DateTime('now');
$startDate = $now->format('Y-m-01');
$endDate = $now->format('Y-m-t');

$capacity = $planner->getMonthlyCapacityPlan($startDate, $endDate);
$categoryBudgets = $planner->getCategoryBudgetsWithActuals($startDate, $endDate);
$recommendedAllocations = $planner->generateRecommendedBudgets($capacity['effective_income']);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Financial Capacity Calculation Banner -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(30, 27, 75, 0.6) 0%, var(--bg-card) 100%);">
    <div class="card-header">
        <div>
            <h3 class="card-title" style="font-size: 1.25rem;">
                <i data-lucide="calculator" style="color: var(--primary);"></i>
                Salary-Based Money Capacity Formula
            </h3>
            <div class="card-subtitle">Transparent mathematical projection of your monthly discretionary capacity</div>
        </div>
        <div>
            <form action="actions/budget_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Apply recommended standard budget allocations for your income? This will update category budgets.');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="apply_recommended">
                <input type="hidden" name="monthly_income" value="<?= $capacity['effective_income'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="sparkles" style="width: 14px; height: 14px;"></i> Auto-Populate Recommended
                </button>
            </form>
        </div>
    </div>

    <!-- The Equation Formula Bar -->
    <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
        <div style="font-size: 0.76rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; font-weight: 700;">
            The SmartSpend Flexible Spending Equation:
        </div>
        <div style="font-family: monospace; font-size: 0.95rem; color: #f8fafc; line-height: 1.8;">
            <span style="color: var(--success); font-weight: 700;">Expected Income (<?= formatCurrency($capacity['effective_income'], $currency) ?>)</span>
            <span style="color: var(--text-muted);"> − </span>
            <span style="color: var(--danger); font-weight: 700;">Essential Living Costs (<?= formatCurrency($capacity['essential_budget_total'], $currency) ?>)</span>
            <span style="color: var(--text-muted);"> − </span>
            <span style="color: var(--warning); font-weight: 700;">Unpaid Separate Bills (<?= formatCurrency($capacity['unpaid_separate_bills'], $currency) ?>)</span>
            <span style="color: var(--text-muted);"> − </span>
            <span style="color: #8b5cf6; font-weight: 700;">Planned Savings (<?= formatCurrency($capacity['planned_savings_target'], $currency) ?>)</span>
            <span style="color: var(--text-muted);"> = </span>
            <span style="color: #38bdf8; font-weight: 800; font-size: 1.1rem;">Projected Flexible Spending: <?= formatCurrency($capacity['projected_flexible_total'], $currency) ?></span>
        </div>
    </div>

    <!-- 4 KPI Breakdown Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
        <div style="padding: 14px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px;">
            <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Remaining Flexible Budget</div>
            <div style="font-size: 1.35rem; font-weight: 700; color: #38bdf8; margin-top: 4px;">
                <?= formatCurrency($capacity['remaining_flexible'], $currency) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                Discretionary spent: <?= formatCurrency($capacity['discretionary_spent'], $currency) ?>
            </div>
        </div>

        <div style="padding: 14px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px;">
            <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Safe Daily Allowance</div>
            <div style="font-size: 1.35rem; font-weight: 700; color: var(--primary); margin-top: 4px;">
                <?= formatCurrency($capacity['daily_discretionary'], $currency) ?>/day
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                Across remaining <?= $capacity['remaining_days'] ?> days this month
            </div>
        </div>

        <div style="padding: 14px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px;">
            <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Total Living Commitments</div>
            <div style="font-size: 1.35rem; font-weight: 700; color: var(--danger); margin-top: 4px;">
                <?= formatCurrency($capacity['essential_budget_total'] + $capacity['unpaid_separate_bills'], $currency) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                Rent, utilities, groceries, EMIs
            </div>
        </div>

        <div style="padding: 14px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px;">
            <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Monthly Savings Target</div>
            <div style="font-size: 1.35rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">
                <?= formatCurrency($capacity['planned_savings_target'], $currency) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                Target wealth building
            </div>
        </div>
    </div>
</div>

<!-- Category Budgets Table & Progress -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i data-lucide="layers" style="color: var(--primary);"></i> Category Spending Budgets</h3>
            <div class="card-subtitle">Set maximum monthly spending limits per category to control outflow</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Monthly Limit</th>
                    <th>Actual Spent</th>
                    <th>Remaining</th>
                    <th style="width: 25%;">Budget Progress</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryBudgets as $cb): 
                    $pct = $cb['percent_used'];
                    $barClass = $pct > 100 ? 'danger' : ($pct >= 85 ? 'warning' : 'primary');
                ?>
                    <tr>
                        <td>
                            <strong style="color: var(--text-primary);"><?= sanitize($cb['name']) ?></strong>
                        </td>
                        <td>
                            <?php if ($cb['is_essential']): ?>
                                <span class="badge" style="background: var(--danger-light); color: var(--danger);">Essential Living</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-secondary);">Discretionary</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;">
                            <?= $cb['budget_amount'] > 0 ? formatCurrency($cb['budget_amount'], $currency) : '<span style="color: var(--text-muted);">Not set</span>' ?>
                        </td>
                        <td style="font-weight: 700; color: <?= $cb['spent_amount'] > $cb['budget_amount'] && $cb['budget_amount'] > 0 ? 'var(--danger)' : 'var(--text-primary)' ?>;">
                            <?= formatCurrency($cb['spent_amount'], $currency) ?>
                        </td>
                        <td>
                            <?php if ($cb['budget_amount'] > 0): ?>
                                <?php if ($cb['remaining'] < 0): ?>
                                    <span style="color: var(--danger); font-weight: 700;">-<?= formatCurrency(abs($cb['remaining']), $currency) ?> Over</span>
                                <?php else: ?>
                                    <span style="color: var(--success); font-weight: 600;"><?= formatCurrency($cb['remaining'], $currency) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cb['budget_amount'] > 0): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.74rem; color: var(--text-muted); margin-bottom: 2px;">
                                    <span><?= $pct ?>% used</span>
                                    <span><?= formatCurrency($cb['spent_amount'], $currency) ?> / <?= formatCurrency($cb['budget_amount'], $currency) ?></span>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar <?= $barClass ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">No budget configured</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openBudgetModal(<?= $cb['category_id'] ?>, '<?= addslashes(sanitize($cb['name'])) ?>', <?= (float)$cb['budget_amount'] ?>)">
                                <i data-lucide="edit" style="width: 13px; height: 13px;"></i> Set Limit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Budget Modal -->
<div class="modal-backdrop" id="editBudgetModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Set Category Budget</h3>
            <button class="modal-close" type="button" onclick="closeModal('editBudgetModal')">&times;</button>
        </div>
        <form action="actions/budget_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_budget">
            <input type="hidden" name="category_id" id="budgetCategoryId" value="">

            <div class="modal-body">
                <div style="padding: 12px; background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <span style="font-size: 0.76rem; color: var(--text-muted); text-transform: uppercase;">Category</span>
                    <h4 id="budgetCategoryName" style="color: var(--text-primary); margin-top: 2px;">—</h4>
                </div>

                <div class="form-group">
                    <label class="form-label" for="monthly_amount">Monthly Spending Limit (<?= $currency ?>)</label>
                    <input type="number" step="10" min="0" name="monthly_amount" id="budgetMonthlyAmount" class="form-control" placeholder="0.00" required autofocus>
                    <small class="form-text">Enter 0 to clear or disable the budget for this category.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBudgetModal(catId, catName, currentAmount) {
    document.getElementById('budgetCategoryId').value = catId;
    document.getElementById('budgetCategoryName').textContent = catName;
    document.getElementById('budgetMonthlyAmount').value = currentAmount > 0 ? currentAmount : '';
    openModal('editBudgetModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
