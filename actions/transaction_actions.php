<?php
/**
 * Transaction Actions Controller
 * Handles creation, updates, deletion, and CSV export of financial transactions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSV Export (GET request with session validation)
if ($action === 'export_csv') {
    try {
        $pdo = getDbConnection();
        $type = $_GET['type'] ?? '';
        $categoryId = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $search = trim($_GET['search'] ?? '');

        $sql = '
            SELECT t.id, t.transaction_date, t.type, t.description, t.amount, 
                   c.name as category_name, t.payment_method, t.notes, t.created_at
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = :uid
        ';
        $params = ['uid' => $userId];

        if ($type === 'income' || $type === 'expense') {
            $sql .= ' AND t.type = :type';
            $params['type'] = $type;
        }
        if ($categoryId) {
            $sql .= ' AND t.category_id = :cid';
            $params['cid'] = $categoryId;
        }
        if ($startDate && $endDate) {
            $sql .= ' AND t.transaction_date BETWEEN :start AND :end';
            $params['start'] = $startDate;
            $params['end'] = $endDate;
        }
        if ($search !== '') {
            $sql .= ' AND (t.description LIKE :q OR t.notes LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Output CSV Headers
        $filename = 'smartspend_transactions_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Transaction ID', 'Date', 'Type', 'Description', 'Amount', 'Category', 'Payment Method', 'Notes', 'Recorded At']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['transaction_date'],
                strtoupper($row['type']),
                $row['description'],
                $row['amount'],
                $row['category_name'],
                getPaymentMethodLabel($row['payment_method']),
                $row['notes'] ?? '',
                $row['created_at'],
            ]);
        }

        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log('CSV Export error: ' . $e->getMessage());
        flash('danger', 'Failed to export CSV: ' . $e->getMessage());
        redirect('../transactions.php');
    }
}

// State-Changing POST Requests
validateCsrfOrDie();
$pdo = getDbConnection();

if ($action === 'create') {
    $type = $_POST['type'] ?? 'expense';
    $amount = (float) ($_POST['amount'] ?? 0.0);
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'upi';
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        flash('danger', 'Transaction amount must be greater than zero.');
        redirect('../transactions.php');
    }
    if (empty($description)) {
        flash('danger', 'Description is required.');
        redirect('../transactions.php');
    }

    try {
        // Validate category belongs to user or is system
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :cid AND (user_id = :uid OR user_id IS NULL) LIMIT 1');
        $stmt->execute(['cid' => $categoryId, 'uid' => $userId]);
        if (!$stmt->fetch()) {
            flash('danger', 'Invalid or unauthorized category selected.');
            redirect('../transactions.php');
        }

        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date, payment_method, notes)
            VALUES (:uid, :cid, :type, :amt, :desc, :tdate, :method, :notes)
        ');
        $stmt->execute([
            'uid'    => $userId,
            'cid'    => $categoryId,
            'type'   => $type === 'income' ? 'income' : 'expense',
            'amt'    => $amount,
            'desc'   => $description,
            'tdate'  => $transactionDate,
            'method' => $paymentMethod,
            'notes'  => $notes ?: null,
        ]);

        flash('success', ucfirst($type) . ' transaction of ' . formatCurrency($amount, '₹') . ' recorded successfully.');
        redirect('../transactions.php');
    } catch (Exception $e) {
        error_log('Create transaction error: ' . $e->getMessage());
        flash('danger', 'Error recording transaction: ' . $e->getMessage());
        redirect('../transactions.php');
    }
}

if ($action === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? 'expense';
    $amount = (float) ($_POST['amount'] ?? 0.0);
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'upi';
    $notes = trim($_POST['notes'] ?? '');

    if ($id <= 0 || $amount <= 0 || empty($description)) {
        flash('danger', 'Invalid input for editing transaction.');
        redirect('../transactions.php');
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE transactions 
            SET category_id = :cid, type = :type, amount = :amt, description = :desc, 
                transaction_date = :tdate, payment_method = :method, notes = :notes, updated_at = NOW()
            WHERE id = :id AND user_id = :uid
        ');
        $stmt->execute([
            'cid'    => $categoryId,
            'type'   => $type === 'income' ? 'income' : 'expense',
            'amt'    => $amount,
            'desc'   => $description,
            'tdate'  => $transactionDate,
            'method' => $paymentMethod,
            'notes'  => $notes ?: null,
            'id'     => $id,
            'uid'    => $userId,
        ]);

        flash('success', 'Transaction updated successfully.');
        redirect('../transactions.php');
    } catch (Exception $e) {
        error_log('Edit transaction error: ' . $e->getMessage());
        flash('danger', 'Error updating transaction: ' . $e->getMessage());
        redirect('../transactions.php');
    }
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        flash('danger', 'Invalid transaction ID.');
        redirect('../transactions.php');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);

        flash('success', 'Transaction deleted successfully.');
        redirect('../transactions.php');
    } catch (Exception $e) {
        error_log('Delete transaction error: ' . $e->getMessage());
        flash('danger', 'Error deleting transaction: ' . $e->getMessage());
        redirect('../transactions.php');
    }
}

redirect('../transactions.php');
