<?php
/**
 * SmartSpend Master Footer Template
 * Contains global application modals and essential scripts.
 */

declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

// Fetch user's categories for the quick modals
$modalCategories = [];
$userId = getCurrentUserId();
if ($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT id, name, type, icon, color 
            FROM categories 
            WHERE user_id = :uid OR user_id IS NULL 
            ORDER BY type ASC, name ASC
        ');
        $stmt->execute(['uid' => $userId]);
        $modalCategories = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error loading modal categories: ' . $e->getMessage());
    }
}
$todayDate = date('Y-m-d');
?>
        </main> <!-- /.page-content -->
    </div> <!-- /.app-main -->
</div> <!-- /.app-container -->

<!-- =====================================================================
     GLOBAL MODALS
     ===================================================================== -->

<!-- 1. Quick Add Transaction Modal -->
<div class="modal-backdrop" id="quickTransactionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Transaction</h3>
            <button class="modal-close" type="button">&times;</button>
        </div>
        <form action="actions/transaction_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Transaction Type</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="type" value="expense" checked onchange="toggleCategoryOptions('expense')">
                            <span style="font-weight: 600; color: var(--danger);">Expense</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="type" value="income" onchange="toggleCategoryOptions('income')">
                            <span style="font-weight: 600; color: var(--success);">Income</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="txnAmount">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="txnAmount" class="form-control" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="txnDesc">Description</label>
                    <input type="text" name="description" id="txnDesc" class="form-control" placeholder="e.g. Grocery shopping, Monthly salary, Coffee" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="txnCategory">Category</label>
                        <select name="category_id" id="txnCategory" class="form-select" required>
                            <?php foreach ($modalCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" data-type="<?= $cat['type'] ?>" <?= $cat['type'] === 'income' ? 'style="display:none;"' : '' ?>>
                                    <?= sanitize($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="txnDate">Date</label>
                        <input type="date" name="transaction_date" id="txnDate" class="form-control" value="<?= $todayDate ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="txnMethod">Payment Method</label>
                    <select name="payment_method" id="txnMethod" class="form-select">
                        <option value="upi" selected>UPI (GPay / PhonePe / Paytm)</option>
                        <option value="bank_transfer">Bank Transfer (NEFT/IMPS)</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="txnNotes">Notes (Optional)</label>
                    <textarea name="notes" id="txnNotes" class="form-control" rows="2" placeholder="Add additional details or receipt ref"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('quickTransactionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. "Can I Afford This?" Purchase Calculator Modal -->
<div class="modal-backdrop" id="affordabilityCalculatorModal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="help-circle" style="color: var(--primary);"></i>
                Can I Afford This?
            </h3>
            <button class="modal-close" type="button">&times;</button>
        </div>
        <form id="affordabilityCalculatorForm">
            <?= csrfField() ?>
            <div class="modal-body">
                <p style="font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 16px;">
                    Enter a planned expense. Our engine checks your remaining flexible spending, upcoming unpaid bills, and monthly savings targets to calculate realistic affordability.
                </p>

                <div class="form-group">
                    <label class="form-label">Purchase Item / Purpose</label>
                    <input type="text" name="purchase_name" class="form-control" placeholder="e.g. Sony WH-1000XM5 Headphones, Weekend Trip, New Watch" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Estimated Price (₹)</label>
                        <input type="number" step="0.01" min="1" name="price" class="form-control" placeholder="12500.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Intended Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= $todayDate ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Purchase Classification</label>
                    <select name="is_essential" class="form-select">
                        <option value="0">Optional / Discretionary (Lifestyle, gadget, vacation)</option>
                        <option value="1">Essential / Urgent (Medical, vehicle repair, home fix)</option>
                    </select>
                </div>

                <div id="affordabilityResults" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('affordabilityCalculatorModal')">Close</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="calculator" style="width: 16px; height: 16px;"></i>
                    Evaluate Affordability
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Record Bill Payment Confirmation Modal -->
<div class="modal-backdrop" id="recordBillPaymentModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Bill Payment</h3>
            <button class="modal-close" type="button">&times;</button>
        </div>
        <form action="actions/bill_actions.php" method="POST" id="recordBillPaymentForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="bill_id" value="">
            <div class="modal-body">
                <div style="padding: 14px; background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase;">Bill Name</div>
                    <div id="payBillModalName" style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary);">—</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Due Date: <span id="payBillModalDue">—</span></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Actual Amount Paid (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount_paid" class="form-control" required>
                    <small class="form-text">Adjust this if the actual billed amount differs from the expected amount.</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="paid_date" class="form-control" value="<?= $todayDate ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="upi" selected>UPI</option>
                            <option value="bank_transfer">Net Banking / Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Reference / UTR (Optional)</label>
                    <input type="text" name="payment_reference" class="form-control" placeholder="e.g. UPI Ref / Transaction No.">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional notes for this payment">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('recordBillPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i>
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Category toggle script for transaction modal -->
<script>
function toggleCategoryOptions(selectedType) {
    const select = document.getElementById('txnCategory');
    if (!select) return;
    let firstVisible = null;
    Array.from(select.options).forEach(opt => {
        if (opt.getAttribute('data-type') === selectedType) {
            opt.style.display = '';
            if (!firstVisible) firstVisible = opt;
        } else {
            opt.style.display = 'none';
        }
    });
    if (firstVisible) {
        select.value = firstVisible.value;
    }
}
</script>

<!-- App Scripts -->
<script src="assets/js/app.js"></script>
<script src="assets/js/charts.js"></script>
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
</body>
</html>
