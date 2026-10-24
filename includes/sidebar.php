<?php
/**
 * SmartSpend Sidebar Navigation Component
 */

declare(strict_types=1);

$activePage = $activePage ?? 'dashboard';
$user = getCurrentUser();
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand / Logo -->
    <div class="sidebar-header">
        <a href="dashboard.php" class="brand-logo">
            <div class="brand-icon">
                <i data-lucide="wallet-cards" style="width: 20px; height: 20px;"></i>
            </div>
            <span>SmartSpend</span>
        </a>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-label">Core Overview</div>
        <a href="dashboard.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>
        <a href="transactions.php" class="nav-item <?= $activePage === 'transactions' ? 'active' : '' ?>">
            <i data-lucide="arrow-left-right"></i>
            <span>Transactions</span>
        </a>

        <div class="nav-label">Financial Planning</div>
        <a href="smart_budget.php" class="nav-item <?= $activePage === 'smart_budget' ? 'active' : '' ?>">
            <i data-lucide="pie-chart"></i>
            <span>Smart Budget</span>
        </a>
        <a href="bills.php" class="nav-item <?= $activePage === 'bills' ? 'active' : '' ?>">
            <i data-lucide="calendar-clock"></i>
            <span>Bills & Payments</span>
        </a>
        <a href="bill_calendar.php" class="nav-item <?= $activePage === 'calendar' ? 'active' : '' ?>">
            <i data-lucide="calendar"></i>
            <span>Calendar</span>
        </a>
        <a href="savings_goals.php" class="nav-item <?= $activePage === 'savings' ? 'active' : '' ?>">
            <i data-lucide="target"></i>
            <span>Savings Goals</span>
        </a>

        <div class="nav-label">Intelligence & Analytics</div>
        <a href="insights.php" class="nav-item <?= $activePage === 'insights' ? 'active' : '' ?>">
            <i data-lucide="zap"></i>
            <span>Financial Insights</span>
        </a>
        <a href="reports.php" class="nav-item <?= $activePage === 'reports' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-3"></i>
            <span>Reports</span>
        </a>

        <div class="nav-label">Preferences</div>
        <a href="categories.php" class="nav-item <?= $activePage === 'categories' ? 'active' : '' ?>">
            <i data-lucide="tags"></i>
            <span>Categories</span>
        </a>
        <a href="settings.php" class="nav-item <?= $activePage === 'settings' ? 'active' : '' ?>">
            <i data-lucide="settings"></i>
            <span>Settings</span>
        </a>
    </nav>

    <!-- Sidebar Bottom User Card -->
    <div class="sidebar-footer">
        <div class="user-mini-card">
            <div class="user-avatar">
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-meta">
                <div class="user-name"><?= sanitize($user['full_name'] ?? 'User Account') ?></div>
                <div class="user-sub"><?= sanitize($user['email'] ?? '') ?></div>
            </div>
            <a href="logout.php" title="Sign Out" style="color: var(--text-muted); padding: 4px; display: flex; align-items: center;" onclick="return confirm('Are you sure you want to log out?');">
                <i data-lucide="log-out" style="width: 17px; height: 17px;"></i>
            </a>
        </div>
    </div>
</aside>
