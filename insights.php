<?php
/**
 * SmartSpend Financial Insights & Spending Analysis
 */

declare(strict_types=1);

$pageTitle = 'Financial Insights';
$activePage = 'insights';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/FinancialCalculations.php';
require_once __DIR__ . '/services/BudgetPlanner.php';
require_once __DIR__ . '/services/FinancialInsights.php';

$pdo = getDbConnection();
$calc = new FinancialCalculations($pdo, $userId);
$insightsEngine = new FinancialInsights($pdo, $userId, $currency);

$allInsights = $insightsEngine->generateInsights();

// Fetch recurring subscription commitments for audit
$stmt = $pdo->prepare('
    SELECT b.*, c.name as category_name
    FROM recurring_bills b
    JOIN categories c ON c.id = b.category_id
    WHERE b.user_id = :uid
      AND b.status = "active"
      AND (c.name LIKE "%Subscription%" OR b.name LIKE "%Netflix%" OR b.name LIKE "%Spotify%" OR b.name LIKE "%Prime%" OR b.name LIKE "%Hotstar%" OR b.name LIKE "%Gym%" OR b.name LIKE "%Cloud%" OR b.name LIKE "%SaaS%")
    ORDER BY b.amount DESC
');
$stmt->execute(['uid' => $userId]);
$subscriptions = $stmt->fetchAll();
$totalSubMonthly = 0.0;
foreach ($subscriptions as $s) {
    $totalSubMonthly += (float) $s['amount'];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Insights Header Introduction -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, var(--bg-card) 100%);">
    <div class="card-header" style="margin-bottom: 8px;">
        <h3 class="card-title" style="font-size: 1.25rem;">
            <i data-lucide="zap" style="color: var(--primary);"></i>
            Smart Financial Intelligence & Behavior Analysis
        </h3>
    </div>
    <p style="font-size: 0.88rem; color: var(--text-secondary); max-width: 800px; margin: 0;">
        SmartSpend continuously analyzes your real transaction ledger, recurring bills, and budget pacing using transparent, deterministic rules. Every insight below compares identical historical periods to help you curb leakages and reach financial milestones faster.
    </p>
</div>

<!-- Active Actionable Insights Grid -->
<div style="margin-bottom: 32px;">
    <h3 style="font-size: 1.15rem; color: var(--text-primary); margin-bottom: 16px;">
        Current Pacing & Behavioral Observations (<?= count($allInsights) ?>)
    </h3>

    <?php if (empty($allInsights)): ?>
        <div class="card empty-state">
            <div class="empty-state-icon"><i data-lucide="check-circle-2"></i></div>
            <div class="empty-state-title">Finances Looking Healthy</div>
            <div class="empty-state-text">No irregular spending surges or severe budget overruns detected for this period. Keep maintaining your steady cash flow!</div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($allInsights as $insight): ?>
                <div class="insight-feed-card <?= sanitize($insight['type']) ?>" style="padding: 20px; border-radius: 12px;">
                    <div class="insight-feed-icon" style="width: 40px; height: 40px;">
                        <i data-lucide="<?= sanitize($insight['icon']) ?>" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div class="insight-feed-body">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <h4 style="font-size: 1.05rem; color: var(--text-primary); margin: 0;"><?= sanitize($insight['title']) ?></h4>
                            <span class="badge badge-<?= sanitize($insight['type']) ?>" style="text-transform: uppercase;">
                                <?= ucfirst($insight['type']) ?>
                            </span>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 12px; line-height: 1.5;">
                            <?= sanitize($insight['description']) ?>
                        </p>
                        <?php if (!empty($insight['action_text'])): ?>
                            <a href="<?= sanitize($insight['action_url']) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                                <span><?= sanitize($insight['action_text']) ?></span>
                                <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Active Recurring Subscriptions Audit Section -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i data-lucide="tv" style="color: var(--primary);"></i> Subscriptions & Media Audit</h3>
            <div class="card-subtitle">Recurring digital memberships and services that auto-debit each cycle</div>
        </div>
        <div>
            <span style="font-size: 0.88rem; color: var(--text-muted);">Monthly Subscription Outflow: </span>
            <strong style="color: var(--warning); font-size: 1.1rem;"><?= formatCurrency($totalSubMonthly, $currency) ?>/month</strong>
        </div>
    </div>

    <?php if (empty($subscriptions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="check"></i></div>
            <div class="empty-state-title">No Recurring Subscriptions Found</div>
            <div class="empty-state-text">You have no active subscription commitments recorded in your bills ledger.</div>
            <a href="bills.php" class="btn btn-outline btn-sm">Add Subscription Bill</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subscription Service</th>
                        <th>Category</th>
                        <th>Frequency</th>
                        <th>Next Due Date</th>
                        <th style="text-align: right;">Cost</th>
                        <th style="text-align: right;">Annual Cost Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $s): 
                        $annualCost = (float)$s['amount'] * 12;
                    ?>
                        <tr>
                            <td><strong style="color: var(--text-primary);"><?= sanitize($s['name']) ?></strong></td>
                            <td><span class="badge badge-neutral"><?= sanitize($s['category_name']) ?></span></td>
                            <td style="font-size: 0.82rem;"><?= getFrequencyLabel($s['frequency']) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.84rem;"><?= formatDate($s['next_due_date']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--text-primary);"><?= formatCurrency($s['amount'], $currency) ?></td>
                            <td style="text-align: right; font-weight: 600; color: var(--warning);"><?= formatCurrency($annualCost, $currency) ?>/yr</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
