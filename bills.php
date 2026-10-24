<?php
/**
 * SmartSpend Recurring Bills & Payments Manager
 */

declare(strict_types=1);

$pageTitle = 'Bills & Payments Manager';
$activePage = 'bills';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/services/BillRecurrence.php';

$pdo = getDbConnection();
$billService = new BillRecurrence($pdo, $userId);

$categorized = $billService->getCategorizedBills();
$groups = $categorized['groups'];
$totals = $categorized['totals'];
$recentPayments = $billService->getPaymentHistory(null, 15);

// Fetch expense categories for Add Bill modal
$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE (user_id = :uid OR user_id IS NULL) AND type = "expense" ORDER BY name ASC');
$stmt->execute(['uid' => $userId]);
$billCategories = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Bills Overview Totals -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <!-- Overdue Total -->
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid <?= $totals['overdue'] > 0 ? 'rgba(239, 68, 68, 0.4)' : 'var(--border-color)' ?>;">
        <div style="font-size: 0.74rem; color: var(--danger); text-transform: uppercase; font-weight: 700;">Overdue Bills</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--danger); margin-top: 4px;">
            <?= formatCurrency($totals['overdue'], $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Requires immediate payment</div>
    </div>

    <!-- Due This Month -->
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--warning); text-transform: uppercase; font-weight: 700;">Due This Month</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--warning); margin-top: 4px;">
            <?= formatCurrency($totals['due_this_month'], $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Unpaid monthly obligations</div>
    </div>

    <!-- Monthly Recurring Commitment Run-Rate -->
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--primary); text-transform: uppercase; font-weight: 700;">Monthly Run-Rate</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: var(--primary); margin-top: 4px;">
            <?= formatCurrency($totals['monthly_run_rate'], $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Normalized monthly bill cost</div>
    </div>

    <!-- Estimated Annual Commitment -->
    <div style="padding: 16px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: #8b5cf6; text-transform: uppercase; font-weight: 700;">Annual Total Run-Rate</div>
        <div style="font-size: 1.45rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">
            <?= formatCurrency($totals['yearly_run_rate'], $currency) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Includes yearly & quarterly subs</div>
    </div>
</div>

<!-- Header Controls -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
        <a href="bill_calendar.php" class="btn btn-outline btn-sm">
            <i data-lucide="calendar" style="width: 14px; height: 14px;"></i> View Monthly Calendar
        </a>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addBillModal')">
            <i data-lucide="plus" style="width: 15px; height: 15px;"></i> Add Recurring Bill
        </button>
    </div>
</div>

<!-- Overdue Bills Section (if any) -->
<?php if (!empty($groups['overdue'])): ?>
    <div class="card" style="margin-bottom: 24px; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.03);">
        <div class="card-header">
            <h3 class="card-title" style="color: var(--danger);">
                <i data-lucide="alert-octagon"></i> Overdue Bills (<?= count($groups['overdue']) ?>)
            </h3>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($groups['overdue'] as $b): 
                $status = getDueStatus($b['next_due_date']);
            ?>
                <div class="bill-widget-item" style="border-left: 4px solid var(--danger);">
                    <div class="bill-widget-left">
                        <div class="txn-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i data-lucide="<?= $b['category_icon'] ?: 'alert-circle' ?>"></i>
                        </div>
                        <div>
                            <div class="bill-widget-name"><?= sanitize($b['name']) ?></div>
                            <div class="bill-widget-due status-danger"><?= $status['text'] ?> (Was due <?= formatDate($b['next_due_date']) ?>)</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <span class="badge badge-neutral"><?= getFrequencyLabel($b['frequency']) ?></span>
                        <div style="font-size: 1.1rem; font-weight: 700; color: var(--danger);">
                            <?= formatCurrency($b['amount'], $currency) ?>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="openPayBillModal(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['name'])) ?>', <?= (float)$b['amount'] ?>, '<?= $b['next_due_date'] ?>')">
                            Record Payment
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Due Today & Due Soon Section -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="calendar-check" style="color: var(--warning);"></i> Due Soon & This Month</h3>
    </div>

    <?php 
    $imminentBills = array_merge($groups['due_today'], $groups['due_soon'], $groups['due_later_month']);
    if (empty($imminentBills)): 
    ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="smile"></i></div>
            <div class="empty-state-title">No Pending Bills This Month</div>
            <div class="empty-state-text">You are all caught up on scheduled recurring obligations for this month!</div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($imminentBills as $b): 
                $status = getDueStatus($b['next_due_date']);
            ?>
                <div class="bill-widget-item">
                    <div class="bill-widget-left">
                        <div class="txn-icon" style="background: rgba(255,255,255,0.06); color: <?= $b['category_color'] ?: 'var(--primary)' ?>;">
                            <i data-lucide="<?= $b['category_icon'] ?: 'receipt' ?>"></i>
                        </div>
                        <div>
                            <div class="bill-widget-name"><?= sanitize($b['name']) ?></div>
                            <div class="bill-widget-due <?= $status['class'] ?>">
                                <?= $status['text'] ?> • Due day: <?= $b['due_day'] ?>th
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <span class="badge badge-neutral"><?= getFrequencyLabel($b['frequency']) ?></span>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary);">
                            <?= formatCurrency($b['amount'], $currency) ?>
                        </div>
                        <button class="btn btn-success btn-sm" onclick="openPayBillModal(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['name'])) ?>', <?= (float)$b['amount'] ?>, '<?= $b['next_due_date'] ?>')">
                            Record Payment
                        </button>
                        <form action="actions/bill_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this recurring bill?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_bill">
                            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="padding: 6px; color: var(--text-muted);" title="Delete Bill">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Upcoming Yearly Bills Section -->
<?php if (!empty($groups['upcoming_yearly'])): ?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="shield" style="color: #06b6d4;"></i> Upcoming Annual Commitments</h3>
            <div class="card-subtitle">Yearly bills scheduled later in the year</div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($groups['upcoming_yearly'] as $b): ?>
                <div class="bill-widget-item">
                    <div class="bill-widget-left">
                        <div class="txn-icon" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div>
                            <div class="bill-widget-name"><?= sanitize($b['name']) ?></div>
                            <div class="bill-widget-due status-neutral">Due on <?= formatDate($b['next_due_date']) ?></div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <span class="badge badge-info">Annual (Yearly)</span>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary);">
                            <?= formatCurrency($b['amount'], $currency) ?>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="openPayBillModal(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['name'])) ?>', <?= (float)$b['amount'] ?>, '<?= $b['next_due_date'] ?>')">
                            Pay Early
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Payment History Audit Trail -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="history" style="color: var(--primary);"></i> Recent Bill Payment History</h3>
        <div class="card-subtitle">Atomic payment logs automatically synced with expense transactions</div>
    </div>

    <?php if (empty($recentPayments)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="file-check"></i></div>
            <div class="empty-state-title">No Payment Records Yet</div>
            <div class="empty-state-text">When you record payments for your bills, the audit history and linked expense entries will show here.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bill Name</th>
                        <th>Category</th>
                        <th>Billed Due Date</th>
                        <th>Date Paid</th>
                        <th>Method</th>
                        <th>Reference / Notes</th>
                        <th style="text-align: right;">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><strong style="color: var(--text-primary);"><?= sanitize($p['bill_name']) ?></strong></td>
                            <td><span class="badge badge-neutral"><?= sanitize($p['category_name']) ?></span></td>
                            <td style="color: var(--text-muted); font-size: 0.82rem;"><?= formatDate($p['due_date']) ?></td>
                            <td style="color: var(--success); font-weight: 600; font-size: 0.84rem;"><?= formatDate($p['paid_date']) ?></td>
                            <td style="font-size: 0.82rem;"><?= getPaymentMethodLabel($p['payment_method']) ?></td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);"><?= sanitize($p['payment_reference'] ?: ($p['notes'] ?: '—')) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--text-primary);"><?= formatCurrency($p['amount_paid'], $currency) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Recurring Bill Modal -->
<div class="modal-backdrop" id="addBillModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add Recurring Bill</h3>
            <button class="modal-close" type="button" onclick="closeModal('addBillModal')">&times;</button>
        </div>
        <form action="actions/bill_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_bill">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Bill / Obligation Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Electricity Bill, Netflix Premium, Home Loan EMI" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($billCategories as $bc): ?>
                                <option value="<?= $bc['id'] ?>"><?= sanitize($bc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expected Amount (<?= $currency ?>)</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Recurrence Frequency</label>
                        <select name="frequency" class="form-select" required>
                            <option value="monthly" selected>Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="bi_monthly">Every 2 Months</option>
                            <option value="quarterly">Quarterly (3 Months)</option>
                            <option value="half_yearly">Half-Yearly (6 Months)</option>
                            <option value="yearly">Yearly (Annual)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">First / Next Due Date</label>
                        <input type="date" name="first_due_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. Account number, payment link or reminder note">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addBillModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Recurring Bill</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
