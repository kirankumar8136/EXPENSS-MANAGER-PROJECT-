<?php
/**
 * SmartSpend Savings Goals & Milestone Tracker
 */

declare(strict_types=1);

$pageTitle = 'Savings Goals';
$activePage = 'savings';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = getDbConnection();

// Fetch all goals
$stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id = :uid ORDER BY status ASC, priority DESC, target_date ASC');
$stmt->execute(['uid' => $userId]);
$goals = $stmt->fetchAll();

// Calculate totals
$totalTarget = 0.0;
$totalSaved = 0.0;
foreach ($goals as $g) {
    $totalTarget += (float) $g['target_amount'];
    $totalSaved += (float) $g['current_amount'];
}
$overallPercent = getSafePercent($totalSaved, $totalTarget);

// Fetch recent contribution history
$stmt = $pdo->prepare('
    SELECT c.*, g.name as goal_name
    FROM savings_contributions c
    JOIN savings_goals g ON g.id = c.goal_id
    WHERE c.user_id = :uid
    ORDER BY c.date DESC, c.id DESC
    LIMIT 20
');
$stmt->execute(['uid' => $userId]);
$history = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Overview KPI Banner -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Total Target Wealth</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 4px;">
            <?= formatCurrency($totalTarget, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Sum of all active targets</div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Total Accumulated Savings</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">
            <?= formatCurrency($totalSaved, $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?= $overallPercent ?>% of total goal reached</div>
    </div>

    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Remaining To Accumulate</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning); margin-top: 4px;">
            <?= formatCurrency(max(0, $totalTarget - $totalSaved), $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Across <?= count($goals) ?> goals</div>
    </div>
</div>

<!-- Header Controls -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
        <h3 style="font-size: 1.15rem; color: var(--text-primary); margin: 0;">Active Wealth & Purchase Goals</h3>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('createGoalModal')">
        <i data-lucide="plus" style="width: 15px; height: 15px;"></i> Create New Goal
    </button>
</div>

<!-- Goals Cards Grid -->
<?php if (empty($goals)): ?>
    <div class="card empty-state">
        <div class="empty-state-icon"><i data-lucide="target"></i></div>
        <div class="empty-state-title">No Savings Goals Defined</div>
        <div class="empty-state-text">Setting goals helps you prioritize saving for an emergency fund, gadget purchases, or travel.</div>
        <button class="btn btn-primary btn-sm" onclick="openModal('createGoalModal')">Create Your First Goal</button>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-bottom: 32px;">
        <?php foreach ($goals as $g): 
            $target = (float) $g['target_amount'];
            $current = (float) $g['current_amount'];
            $pct = getSafePercent($current, $target);
            $remaining = max(0.0, $target - $current);

            // Compute suggested monthly contribution
            $today = new DateTime('today');
            $targetDt = new DateTime($g['target_date']);
            $monthsLeft = max(1, (int) $today->diff($targetDt)->format('%y') * 12 + (int) $today->diff($targetDt)->format('%m'));
            $suggestedMonthly = round($remaining / $monthsLeft, 2);
            $isComplete = ($g['status'] === 'completed' || $current >= $target);
        ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                <div>
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px;">
                        <div>
                            <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-secondary); margin-bottom: 6px;">
                                <?= sanitize($g['category']) ?>
                            </span>
                            <h4 style="font-size: 1.1rem; color: var(--text-primary); margin: 0;"><?= sanitize($g['name']) ?></h4>
                        </div>
                        <?php if ($isComplete): ?>
                            <span class="badge badge-success">Completed</span>
                        <?php else: ?>
                            <span class="badge badge-warning"><?= ucfirst($g['priority']) ?> Priority</span>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Figures -->
                    <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 16px;">
                        <span style="font-size: 1.4rem; font-weight: 800; color: #8b5cf6;">
                            <?= formatCurrency($current, $currency) ?>
                        </span>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                            of <?= formatCurrency($target, $currency) ?>
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container" style="height: 10px; margin: 10px 0;">
                        <div class="progress-bar <?= $isComplete ? 'success' : 'primary' ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 16px;">
                        <span><?= $pct ?>% funded</span>
                        <span><?= formatCurrency($remaining, $currency) ?> left</span>
                    </div>

                    <?php if (!$isComplete): ?>
                        <div style="padding: 10px 12px; background: rgba(255,255,255,0.03); border-radius: 8px; font-size: 0.78rem; border: 1px solid var(--border-color); margin-bottom: 16px;">
                            <span style="color: var(--text-muted);">Suggested Pace:</span>
                            <strong style="color: var(--primary); margin-left: 4px;">
                                <?= formatCurrency($suggestedMonthly, $currency) ?>/month
                            </strong>
                            <div style="color: var(--text-muted); font-size: 0.72rem; margin-top: 2px;">
                                Target Date: <?= formatDate($g['target_date']) ?> (<?= $monthsLeft ?> mo remaining)
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; pt: 12px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="openContributeModal(<?= $g['id'] ?>, '<?= addslashes(sanitize($g['name'])) ?>')">
                        <i data-lucide="plus-circle" style="width: 14px; height: 14px;"></i> Deposit / Withdraw
                    </button>
                    <form action="actions/savings_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this savings goal?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_goal">
                        <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--text-muted);" title="Delete Goal">
                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Contribution History Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="history" style="color: var(--primary);"></i> Savings Contribution History</h3>
    </div>

    <?php if (empty($history)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
            <div class="empty-state-title">No Deposit History Yet</div>
            <div class="empty-state-text">Contributions recorded to your goals will be tracked here.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Goal Name</th>
                        <th>Type</th>
                        <th>Notes</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.84rem;"><?= formatDate($h['date']) ?></td>
                            <td><strong style="color: var(--text-primary);"><?= sanitize($h['goal_name']) ?></strong></td>
                            <td>
                                <span class="badge <?= $h['type'] === 'deposit' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($h['type']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);"><?= sanitize($h['notes'] ?: '—') ?></td>
                            <td style="text-align: right; font-weight: 700; color: <?= $h['type'] === 'deposit' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $h['type'] === 'deposit' ? '+' : '-' ?><?= formatCurrency($h['amount'], $currency) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create Goal Modal -->
<div class="modal-backdrop" id="createGoalModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Savings Goal</h3>
            <button class="modal-close" type="button" onclick="closeModal('createGoalModal')">&times;</button>
        </div>
        <form action="actions/savings_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_goal">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Goal Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. MacBook Pro M3, Europe Trip, Emergency Fund" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Target Amount (<?= $currency ?>)</label>
                        <input type="number" step="0.01" min="100" name="target_amount" class="form-control" placeholder="50000.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Initial Amount Already Saved</label>
                        <input type="number" step="0.01" min="0" name="current_amount" class="form-control" value="0.00">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Target Date</label>
                        <input type="date" name="target_date" class="form-control" value="<?= date('Y-m-d', strtotime('+12 months')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High (Essential)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="Emergency Fund" selected>Emergency Fund</option>
                        <option value="Technology & Gadgets">Technology & Gadgets</option>
                        <option value="Travel & Vacation">Travel & Vacation</option>
                        <option value="Vehicle">Vehicle</option>
                        <option value="Home & Real Estate">Home & Real Estate</option>
                        <option value="Education">Education</option>
                        <option value="General Wealth">General Wealth</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Why are you saving for this?"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createGoalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Goal</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Deposit/Withdrawal Modal -->
<div class="modal-backdrop" id="contributeModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Contribution</h3>
            <button class="modal-close" type="button" onclick="closeModal('contributeModal')">&times;</button>
        </div>
        <form action="actions/savings_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="record_contribution">
            <input type="hidden" name="goal_id" id="contribGoalId" value="">

            <div class="modal-body">
                <div style="padding: 12px; background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <span style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Goal</span>
                    <h4 id="contribGoalName" style="color: var(--text-primary); margin-top: 2px;">—</h4>
                </div>

                <div class="form-group">
                    <label class="form-label">Action Type</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="type" value="deposit" checked>
                            <span style="font-weight: 600; color: var(--success);">Deposit</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="type" value="withdrawal">
                            <span style="font-weight: 600; color: var(--danger);">Withdrawal</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="0.00" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Note (Optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. Monthly salary contribution">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('contributeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
function openContributeModal(goalId, goalName) {
    document.getElementById('contribGoalId').value = goalId;
    document.getElementById('contribGoalName').textContent = goalName;
    openModal('contributeModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
