<?php
/**
 * Budget Actions Controller
 * Saves custom category allocations or populates recommended allocations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/BudgetPlanner.php';

requireLogin();
validateCsrfOrDie();

$userId = getCurrentUserId();
$pdo = getDbConnection();
$planner = new BudgetPlanner($pdo, $userId);
$action = $_POST['action'] ?? '';

if ($action === 'save_budget') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $amount = max(0.0, (float) ($_POST['monthly_amount'] ?? 0.0));

    if ($categoryId <= 0) {
        flash('danger', 'Invalid category for budget.');
        redirect('../smart_budget.php');
    }

    try {
        $planner->setCategoryBudget($categoryId, $amount);
        flash('success', 'Category budget updated to ' . formatCurrency($amount, '₹') . '.');
        redirect('../smart_budget.php');
    } catch (Exception $e) {
        error_log('Save budget error: ' . $e->getMessage());
        flash('danger', 'Failed to update budget: ' . $e->getMessage());
        redirect('../smart_budget.php');
    }
}

if ($action === 'apply_recommended') {
    $monthlyIncome = (float) ($_POST['monthly_income'] ?? 0.0);

    try {
        $recommendations = $planner->generateRecommendedBudgets($monthlyIncome);
        foreach ($recommendations as $rec) {
            $planner->setCategoryBudget($rec['category_id'], $rec['recommended_amount']);
        }
        flash('success', 'Personalized salary-based budget allocations applied successfully.');
        redirect('../smart_budget.php');
    } catch (Exception $e) {
        error_log('Apply recommended budget error: ' . $e->getMessage());
        flash('danger', 'Error applying recommendations: ' . $e->getMessage());
        redirect('../smart_budget.php');
    }
}

redirect('../smart_budget.php');
