<?php
/**
 * SmartSpend Category Management
 */

declare(strict_types=1);

$pageTitle = 'Category Management';
$activePage = 'categories';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = getDbConnection();

// Fetch all categories with transaction count
$stmt = $pdo->prepare('
    SELECT c.*, COUNT(t.id) as transaction_count
    FROM categories c
    LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id = :uid
    WHERE c.user_id = :uid OR c.user_id IS NULL
    GROUP BY c.id
    ORDER BY c.type ASC, c.is_system DESC, c.name ASC
');
$stmt->execute(['uid' => $userId]);
$categories = $stmt->fetchAll();

$expenseCats = array_filter($categories, fn($c) => $c['type'] === 'expense');
$incomeCats = array_filter($categories, fn($c) => $c['type'] === 'income');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Action -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 1.15rem; color: var(--text-primary); margin: 0;">Financial Classifications & Categories</h3>
        <p style="font-size: 0.84rem; color: var(--text-muted); margin: 2px 0 0;">System defaults plus your personalized custom spending categories</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('addCategoryModal')">
        <i data-lucide="plus" style="width: 15px; height: 15px;"></i> Add Custom Category
    </button>
</div>

<!-- Expense Categories Card -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="arrow-up-right" style="color: var(--danger);"></i> Expense Categories (<?= count($expenseCats) ?>)</h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Classification</th>
                    <th>Color Tag</th>
                    <th>Origin</th>
                    <th>Transactions Recorded</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenseCats as $c): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 28px; height: 28px; border-radius: 6px; background: rgba(255,255,255,0.06); color: <?= $c['color'] ?>; display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="<?= $c['icon'] ?: 'tag' ?>" style="width: 15px; height: 15px;"></i>
                                </div>
                                <strong style="color: var(--text-primary);"><?= sanitize($c['name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($c['is_essential']): ?>
                                <span class="badge" style="background: var(--danger-light); color: var(--danger);">Essential Living</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">Discretionary</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 14px; height: 14px; border-radius: var(--radius-pill); background-color: <?= $c['color'] ?>; display: inline-block;"></span>
                                <span style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);"><?= $c['color'] ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($c['is_system']): ?>
                                <span class="badge badge-neutral">System Default</span>
                            <?php else: ?>
                                <span class="badge badge-info">Custom User</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.84rem;">
                            <?= $c['transaction_count'] ?> recorded
                        </td>
                        <td style="text-align: center;">
                            <?php if (!$c['is_system']): ?>
                                <form action="actions/category_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this custom category?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); padding: 4px 8px;">
                                        <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i> Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.76rem;">Default (Protected)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Income Categories Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="arrow-down-left" style="color: var(--success);"></i> Income Categories (<?= count($incomeCats) ?>)</h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Color Tag</th>
                    <th>Origin</th>
                    <th>Transactions Recorded</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomeCats as $c): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 28px; height: 28px; border-radius: 6px; background: rgba(255,255,255,0.06); color: <?= $c['color'] ?>; display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="<?= $c['icon'] ?: 'plus-circle' ?>" style="width: 15px; height: 15px;"></i>
                                </div>
                                <strong style="color: var(--text-primary);"><?= sanitize($c['name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 14px; height: 14px; border-radius: var(--radius-pill); background-color: <?= $c['color'] ?>; display: inline-block;"></span>
                                <span style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);"><?= $c['color'] ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($c['is_system']): ?>
                                <span class="badge badge-neutral">System Default</span>
                            <?php else: ?>
                                <span class="badge badge-info">Custom User</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.84rem;">
                            <?= $c['transaction_count'] ?> recorded
                        </td>
                        <td style="text-align: center;">
                            <?php if (!$c['is_system']): ?>
                                <form action="actions/category_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this custom category?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); padding: 4px 8px;">
                                        <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i> Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.76rem;">Default (Protected)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal-backdrop" id="addCategoryModal">
    <div class="modal-dialog" style="max-width: 460px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Custom Category</h3>
            <button class="modal-close" type="button" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form action="actions/category_actions.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_category">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Pet Care, Gaming, Side Hustle" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Classification Type</label>
                    <select name="type" class="form-select" required>
                        <option value="expense" selected>Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Color Theme</label>
                        <input type="color" name="color" class="form-control" value="#6366f1" style="height: 42px; padding: 4px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icon Name</label>
                        <select name="icon" class="form-select">
                            <option value="tag" selected>Tag</option>
                            <option value="shopping-bag">Shopping Bag</option>
                            <option value="coffee">Coffee</option>
                            <option value="car">Car / Vehicle</option>
                            <option value="heart">Health</option>
                            <option value="home">Home</option>
                            <option value="gamepad">Gaming</option>
                            <option value="plane">Travel</option>
                            <option value="book">Education</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_essential" value="1">
                        <span style="font-weight: 500; font-size: 0.88rem;">Essential Living Expense (Protected from discretionary cuts)</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCategoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
