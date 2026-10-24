<?php
/**
 * SmartSpend Transaction Management & History
 */

declare(strict_types=1);

$pageTitle = 'Transactions Ledger';
$activePage = 'transactions';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = getDbConnection();

// Fetch filter parameters
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$categoryId = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$methodFilter = $_GET['method'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'date';
$sortOrder = strtoupper($_GET['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Base query builder
$where = ['t.user_id = :uid'];
$params = ['uid' => $userId];

if ($typeFilter === 'income' || $typeFilter === 'expense') {
    $where[] = 't.type = :type';
    $params['type'] = $typeFilter;
}
if ($categoryId > 0) {
    $where[] = 't.category_id = :cid';
    $params['cid'] = $categoryId;
}
if ($methodFilter !== '') {
    $where[] = 't.payment_method = :pmethod';
    $params['pmethod'] = $methodFilter;
}
if (!empty($startDate)) {
    $where[] = 't.transaction_date >= :sdate';
    $params['sdate'] = $startDate;
}
if (!empty($endDate)) {
    $where[] = 't.transaction_date <= :edate';
    $params['edate'] = $endDate;
}
if ($search !== '') {
    $where[] = '(t.description LIKE :q OR t.notes LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Count total matching transactions
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE {$whereClause}");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $perPage));

// Sum totals for this filter
$sumStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as filter_income,
        COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as filter_expense
    FROM transactions t 
    WHERE {$whereClause}
");
$sumStmt->execute($params);
$sums = $sumStmt->fetch();
$filteredIncome = (float) $sums['filter_income'];
$filteredExpense = (float) $sums['filter_expense'];

// Sorting order resolution
$orderCol = match ($sortBy) {
    'amount' => 't.amount',
    'description' => 't.description',
    default => 't.transaction_date',
};

// Fetch paginated transactions
$sql = "
    SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE {$whereClause}
    ORDER BY {$orderCol} {$sortOrder}, t.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

// Categories for filter dropdown
$catStmt = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id = :uid OR user_id IS NULL ORDER BY type ASC, name ASC');
$catStmt->execute(['uid' => $userId]);
$allCategories = $catStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Control Card -->
<div class="card" style="margin-bottom: 24px;">
    <form action="transactions.php" method="GET" style="display: flex; flex-direction: column; gap: 14px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 12px;">
            <div>
                <input type="text" name="search" class="form-control" placeholder="Search by description or note..." value="<?= sanitize($search) ?>">
            </div>
            <div>
                <select name="type" class="form-select">
                    <option value="">All Types (Income & Expense)</option>
                    <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>Expenses Only</option>
                    <option value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>Income Only</option>
                </select>
            </div>
            <div>
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                            [<?= ucfirst($c['type']) ?>] <?= sanitize($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="method" class="form-select">
                    <option value="">All Payment Methods</option>
                    <option value="upi" <?= $methodFilter === 'upi' ? 'selected' : '' ?>>UPI</option>
                    <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="credit_card" <?= $methodFilter === 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                    <option value="debit_card" <?= $methodFilter === 'debit_card' ? 'selected' : '' ?>>Debit Card</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                </select>
            </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">From:</span>
                    <input type="date" name="start_date" class="form-control" style="width: auto; padding: 6px 10px;" value="<?= sanitize($startDate) ?>">
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">To:</span>
                    <input type="date" name="end_date" class="form-control" style="width: auto; padding: 6px 10px;" value="<?= sanitize($endDate) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="filter" style="width: 14px; height: 14px;"></i> Apply Filters
                </button>
                <a href="transactions.php" class="btn btn-outline btn-sm">Reset</a>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Export to CSV -->
                <a href="actions/transaction_actions.php?action=export_csv&<?= http_build_query($_GET) ?>" class="btn btn-outline btn-sm" title="Export matching transactions to CSV file">
                    <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export CSV
                </a>
                <!-- New Transaction Modal Trigger -->
                <button type="button" class="btn btn-success btn-sm" onclick="openModal('quickTransactionModal')">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Transaction
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Filtered Summary Banner -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="padding: 14px 18px; background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Filtered Income</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: var(--success); margin-top: 2px;">
            <?= formatCurrency($filteredIncome, $currency) ?>
        </div>
    </div>
    <div style="padding: 14px 18px; background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Filtered Expense</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: var(--danger); margin-top: 2px;">
            <?= formatCurrency($filteredExpense, $currency) ?>
        </div>
    </div>
    <div style="padding: 14px 18px; background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border-color);">
        <div style="font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;">Net Cash Flow</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: <?= $filteredIncome >= $filteredExpense ? 'var(--text-primary)' : 'var(--danger)' ?>; margin-top: 2px;">
            <?= formatCurrency($filteredIncome - $filteredExpense, $currency) ?>
        </div>
    </div>
</div>

<!-- Transactions Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i data-lucide="list" style="color: var(--primary);"></i> 
            Transaction Records (<?= number_format($totalRecords) ?> total)
        </h3>
        <div style="font-size: 0.8rem; color: var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?></div>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="search-x"></i></div>
            <div class="empty-state-title">No Transactions Found</div>
            <div class="empty-state-text">No records match your selected filter criteria. Try broadening your dates or clearing search terms.</div>
            <a href="transactions.php" class="btn btn-outline btn-sm">Clear All Filters</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Payment Method</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.84rem;">
                                <?= formatDate($txn['transaction_date'], 'd M Y') ?>
                            </td>
                            <td>
                                <strong style="color: var(--text-primary);"><?= sanitize($txn['description']) ?></strong>
                                <?php if (!empty($txn['notes'])): ?>
                                    <div style="font-size: 0.76rem; color: var(--text-muted);"><?= sanitize($txn['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: <?= $txn['category_color'] ?: 'var(--text-secondary)' ?>;">
                                    <i data-lucide="<?= $txn['category_icon'] ?: 'tag' ?>" style="width: 12px; height: 12px;"></i>
                                    <?= sanitize($txn['category_name']) ?>
                                </span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.82rem;">
                                <?= getPaymentMethodLabel($txn['payment_method']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-size: 0.95rem; color: <?= $txn['type'] === 'income' ? 'var(--success)' : 'var(--text-primary)' ?>;">
                                <?= $txn['type'] === 'income' ? '+' : '-' ?><?= formatCurrency($txn['amount'], $currency) ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 4px 8px;" onclick="openEditTxnModal(<?= htmlspecialchars(json_encode($txn), ENT_QUOTES) ?>)">
                                    <i data-lucide="edit-2" style="width: 13px; height: 13px;"></i>
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 4px 8px; color: var(--danger);" onclick="confirmDeleteTxn(<?= $txn['id'] ?>, '<?= addslashes(sanitize($txn['description'])) ?>')">
                                    <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="transactions.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.84rem; color: var(--text-muted);">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= $totalRecords ?>
                </div>
                <div>
                    <?php if ($page < $totalPages): ?>
                        <a href="transactions.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Edit Transaction Modal -->
<div class="modal-backdrop" id="editTransactionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Transaction</h3>
            <button class="modal-close" type="button" onclick="closeModal('editTransactionModal')">&times;</button>
        </div>
        <form action="actions/transaction_actions.php" method="POST" id="editTransactionForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editTxnId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" id="editTxnType" class="form-select" required>
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="editTxnAmount" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="editTxnDesc" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="editTxnCategory" class="form-select" required>
                            <?php foreach ($allCategories as $c): ?>
                                <option value="<?= $c['id'] ?>">[<?= ucfirst($c['type']) ?>] <?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" id="editTxnDate" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="editTxnMethod" class="form-select">
                        <option value="upi">UPI</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="editTxnNotes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editTransactionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-backdrop" id="deleteTransactionModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger);">Delete Transaction</h3>
            <button class="modal-close" type="button" onclick="closeModal('deleteTransactionModal')">&times;</button>
        </div>
        <form action="actions/transaction_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteTxnId" value="">
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteTxnDescName" style="color: var(--text-primary);"></strong>?</p>
                <p style="font-size: 0.8rem; color: var(--danger); margin-top: 6px;">This action cannot be undone and will update your dashboard calculations.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteTransactionModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTxnModal(txn) {
    document.getElementById('editTxnId').value = txn.id;
    document.getElementById('editTxnType').value = txn.type;
    document.getElementById('editTxnAmount').value = txn.amount;
    document.getElementById('editTxnDesc').value = txn.description;
    document.getElementById('editTxnCategory').value = txn.category_id;
    document.getElementById('editTxnDate').value = txn.transaction_date;
    document.getElementById('editTxnMethod').value = txn.payment_method;
    document.getElementById('editTxnNotes').value = txn.notes || '';
    openModal('editTransactionModal');
}

function confirmDeleteTxn(id, desc) {
    document.getElementById('deleteTxnId').value = id;
    document.getElementById('deleteTxnDescName').textContent = desc;
    openModal('deleteTransactionModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
