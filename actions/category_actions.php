<?php
/**
 * Category Actions Controller
 * Handles user-defined custom category creation and deletion.
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

if ($action === 'create_category') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] === 'income' ? 'income' : 'expense';
    $color = trim($_POST['color'] ?? '#6366f1');
    $icon = trim($_POST['icon'] ?? 'tag');
    $isEssential = (int) ($_POST['is_essential'] ?? 0);

    if (empty($name)) {
        flash('danger', 'Category name cannot be empty.');
        redirect('../categories.php');
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO categories (user_id, name, type, icon, color, is_system, is_essential)
            VALUES (:uid, :name, :type, :icon, :color, 0, :essential)
        ');
        $stmt->execute([
            'uid'       => $userId,
            'name'      => $name,
            'type'      => $type,
            'icon'      => $icon,
            'color'     => $color,
            'essential' => $isEssential,
        ]);

        flash('success', 'Custom category "' . $name . '" created successfully.');
        redirect('../categories.php');
    } catch (Exception $e) {
        error_log('Create category error: ' . $e->getMessage());
        flash('danger', 'Error adding category: ' . $e->getMessage());
        redirect('../categories.php');
    }
}

if ($action === 'delete_category') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    try {
        // Prevent deletion of system categories or categories belonging to other users
        $stmt = $pdo->prepare('SELECT id, is_system FROM categories WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $categoryId, 'uid' => $userId]);
        $cat = $stmt->fetch();

        if (!$cat || $cat['is_system']) {
            flash('danger', 'System default categories or categories not created by you cannot be deleted.');
            redirect('../categories.php');
        }

        // Check if transactions exist under this category
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = :cid AND user_id = :uid');
        $checkStmt->execute(['cid' => $categoryId, 'uid' => $userId]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            flash('warning', 'Cannot delete this category because transactions are currently assigned to it. Reassign or delete those transactions first.');
            redirect('../categories.php');
        }

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $categoryId, 'uid' => $userId]);

        flash('success', 'Category deleted successfully.');
        redirect('../categories.php');
    } catch (Exception $e) {
        error_log('Delete category error: ' . $e->getMessage());
        flash('danger', 'Error deleting category: ' . $e->getMessage());
        redirect('../categories.php');
    }
}

redirect('../categories.php');
