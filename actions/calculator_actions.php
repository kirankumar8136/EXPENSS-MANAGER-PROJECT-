<?php
/**
 * Affordability Calculator Action Handler
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

$purchaseName = trim($_POST['purchase_name'] ?? '');
$price = (float) ($_POST['price'] ?? 0.0);
$purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
$isEssential = (bool) (int) ($_POST['is_essential'] ?? 0);

if (empty($purchaseName) || $price <= 0) {
    jsonResponse(['success' => false, 'error' => 'Please provide a valid purchase name and price greater than zero.']);
}

try {
    $result = $planner->evaluatePurchaseAffordability($purchaseName, $price, $purchaseDate, $isEssential);
    jsonResponse(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    error_log('Affordability error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Calculation error: ' . $e->getMessage()]);
}
