<?php
/**
 * Savings Goal Actions Controller
 * Handles goals CRUD and recording deposits/withdrawals.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
validateCsrfOrDie();

$userId = getCurrentUserId();
$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

if ($action === 'create_goal') {
    $name = trim($_POST['name'] ?? '');
    $targetAmount = (float) ($_POST['target_amount'] ?? 0.0);
    $currentAmount = (float) ($_POST['current_amount'] ?? 0.0);
    $targetDate = $_POST['target_date'] ?? date('Y-m-d', strtotime('+1 year'));
    $priority = $_POST['priority'] ?? 'medium';
    $monthlyContribution = (float) ($_POST['monthly_contribution_target'] ?? 0.0);
    $category = trim($_POST['category'] ?? 'General');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name) || $targetAmount <= 0) {
        flash('danger', 'Please enter a valid goal name and target amount.');
        redirect('../savings_goals.php');
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, monthly_contribution_target, priority, category, notes, status)
            VALUES (:uid, :name, :tgt, :cur, :tdate, :contrib, :prio, :cat, :notes, "in_progress")
        ');
        $stmt->execute([
            'uid'     => $userId,
            'name'    => $name,
            'tgt'     => $targetAmount,
            'cur'     => $currentAmount,
            'tdate'   => $targetDate,
            'contrib' => $monthlyContribution,
            'prio'    => $priority,
            'cat'     => $category,
            'notes'   => $notes ?: null,
        ]);

        $goalId = (int) $pdo->lastInsertId();

        // If starting with an initial current amount > 0, record deposit history
        if ($currentAmount > 0) {
            $stmt = $pdo->prepare('
                INSERT INTO savings_contributions (goal_id, user_id, type, amount, date, notes)
                VALUES (:gid, :uid, "deposit", :amt, :dt, "Initial opening deposit")
            ');
            $stmt->execute([
                'gid' => $goalId,
                'uid' => $userId,
                'amt' => $currentAmount,
                'dt'  => date('Y-m-d'),
            ]);
        }

        flash('success', 'Savings goal "' . $name . '" created successfully.');
        redirect('../savings_goals.php');
    } catch (Exception $e) {
        error_log('Create goal error: ' . $e->getMessage());
        flash('danger', 'Error creating savings goal: ' . $e->getMessage());
        redirect('../savings_goals.php');
    }
}

if ($action === 'record_contribution') {
    $goalId = (int) ($_POST['goal_id'] ?? 0);
    $type = $_POST['type'] === 'withdrawal' ? 'withdrawal' : 'deposit';
    $amount = (float) ($_POST['amount'] ?? 0.0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($goalId <= 0 || $amount <= 0) {
        flash('danger', 'Invalid contribution amount.');
        redirect('../savings_goals.php');
    }

    try {
        $pdo->beginTransaction();

        // Fetch goal
        $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id = :id AND user_id = :uid LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $goalId, 'uid' => $userId]);
        $goal = $stmt->fetch();

        if (!$goal) {
            $pdo->rollBack();
            flash('danger', 'Goal not found or unauthorized.');
            redirect('../savings_goals.php');
        }

        $current = (float) $goal['current_amount'];
        $newAmount = $type === 'deposit' ? ($current + $amount) : max(0.0, $current - $amount);
        $newStatus = ($newAmount >= (float) $goal['target_amount']) ? 'completed' : 'in_progress';

        // 1. Insert contribution log
        $stmt = $pdo->prepare('
            INSERT INTO savings_contributions (goal_id, user_id, type, amount, date, notes)
            VALUES (:gid, :uid, :type, :amt, :dt, :notes)
        ');
        $stmt->execute([
            'gid'   => $goalId,
            'uid'   => $userId,
            'type'  => $type,
            'amt'   => $amount,
            'dt'    => $date,
            'notes' => $notes ?: null,
        ]);

        // 2. Update goal balance
        $stmt = $pdo->prepare('UPDATE savings_goals SET current_amount = :amt, status = :st, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['amt' => $newAmount, 'st' => $newStatus, 'id' => $goalId]);

        $pdo->commit();
        flash('success', ucfirst($type) . ' of ' . formatCurrency($amount, '₹') . ' recorded successfully.');
        redirect('../savings_goals.php');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Contribution error: ' . $e->getMessage());
        flash('danger', 'Error updating savings goal: ' . $e->getMessage());
        redirect('../savings_goals.php');
    }
}

if ($action === 'delete_goal') {
    $goalId = (int) ($_POST['goal_id'] ?? 0);

    try {
        $stmt = $pdo->prepare('DELETE FROM savings_goals WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $goalId, 'uid' => $userId]);

        flash('success', 'Savings goal deleted successfully.');
        redirect('../savings_goals.php');
    } catch (Exception $e) {
        error_log('Delete goal error: ' . $e->getMessage());
        flash('danger', 'Error deleting savings goal: ' . $e->getMessage());
        redirect('../savings_goals.php');
    }
}

redirect('../savings_goals.php');
