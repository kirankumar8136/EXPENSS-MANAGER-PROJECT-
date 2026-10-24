<?php
/**
 * Recurring Bill Actions Controller
 * Handles creating, updating, archiving bills, and recording payments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/BillRecurrence.php';

requireLogin();
validateCsrfOrDie();

$userId = getCurrentUserId();
$pdo = getDbConnection();
$billService = new BillRecurrence($pdo, $userId);
$action = $_POST['action'] ?? '';

if ($action === 'create_bill') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0.0);
    $isVariable = (int) ($_POST['is_variable_amount'] ?? 0);
    $frequency = $_POST['frequency'] ?? 'monthly';
    $firstDueDate = $_POST['first_due_date'] ?? date('Y-m-d');
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $notes = trim($_POST['notes'] ?? '');

    $dueDay = (int) (new DateTime($firstDueDate))->format('j');

    if (empty($name) || $amount <= 0 || $categoryId <= 0) {
        flash('danger', 'Please enter a valid bill name, positive amount, and category.');
        redirect('../bills.php');
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO recurring_bills (user_id, category_id, name, amount, is_variable_amount, frequency, due_day, next_due_date, start_date, end_date, notes, status)
            VALUES (:uid, :cid, :name, :amt, :isvar, :freq, :dday, :nextdue, :start, :end, :notes, "active")
        ');
        $stmt->execute([
            'uid'     => $userId,
            'cid'     => $categoryId,
            'name'    => $name,
            'amt'     => $amount,
            'isvar'   => $isVariable,
            'freq'    => $frequency,
            'dday'    => $dueDay,
            'nextdue' => $firstDueDate,
            'start'   => $startDate,
            'end'     => $endDate,
            'notes'   => $notes ?: null,
        ]);

        flash('success', 'Recurring bill "' . $name . '" added successfully.');
        redirect('../bills.php');
    } catch (Exception $e) {
        error_log('Create bill error: ' . $e->getMessage());
        flash('danger', 'Error adding bill: ' . $e->getMessage());
        redirect('../bills.php');
    }
}

if ($action === 'pay') {
    $billId = (int) ($_POST['bill_id'] ?? 0);
    $amountPaid = (float) ($_POST['amount_paid'] ?? 0.0);
    $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'upi';
    $paymentRef = trim($_POST['payment_reference'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if ($billId <= 0 || $amountPaid <= 0) {
        flash('danger', 'Invalid payment submission.');
        redirect('../bills.php');
    }

    $res = $billService->recordBillPayment($billId, $amountPaid, $paidDate, $paymentMethod, $paymentRef, $notes);

    if ($res['success']) {
        flash('success', $res['message']);
    } else {
        flash('danger', $res['message']);
    }

    redirect('../bills.php');
}

if ($action === 'delete_bill') {
    $billId = (int) ($_POST['bill_id'] ?? 0);

    try {
        $stmt = $pdo->prepare('DELETE FROM recurring_bills WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $billId, 'uid' => $userId]);

        flash('success', 'Bill removed successfully.');
        redirect('../bills.php');
    } catch (Exception $e) {
        error_log('Delete bill error: ' . $e->getMessage());
        flash('danger', 'Error removing bill: ' . $e->getMessage());
        redirect('../bills.php');
    }
}

redirect('../bills.php');
