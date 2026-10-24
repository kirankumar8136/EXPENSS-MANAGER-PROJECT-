<?php
/**
 * SmartSpend Interactive Bill Calendar View
 */

declare(strict_types=1);

$pageTitle = 'Monthly Bill Calendar';
$activePage = 'calendar';

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$currency = $user['currency'] ?? '₹';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/BillRecurrence.php';

$pdo = getDbConnection();
$billService = new BillRecurrence($pdo, $userId);

// Resolve Year and Month
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDayOfMonth = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int) $firstDayOfMonth->format('t');
$startDayOfWeek = (int) $firstDayOfMonth->format('w'); // 0 (Sun) to 6 (Sat)
$monthLabel = $firstDayOfMonth->format('F Y');

// Previous & Next navigation dates
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Fetch bills for this calendar month
$bills = $billService->getBillsForCalendarMonth($year, $month);

// Index bills by day of month (1 to $daysInMonth)
$billsByDay = [];
$totalDueThisMonth = 0.0;
$totalPaidThisMonth = 0.0;
$totalBillCount = count($bills);

foreach ($bills as $b) {
    $day = (int) (new DateTime($b['date']))->format('j');
    $billsByDay[$day][] = $b;
    if ($b['is_paid']) {
        $totalPaidThisMonth += (float) ($b['amount_paid'] ?: $b['amount']);
    } else {
        $totalDueThisMonth += (float) $b['amount'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}
.calendar-day-header {
    text-align: center;
    padding: 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
}
.calendar-cell {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    min-height: 110px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
}
.calendar-cell.today {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}
.calendar-cell.empty {
    background: transparent;
    border-color: transparent;
}
.cell-day-num {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 6px;
}
.cell-day-num.today {
    color: #fff;
    background: var(--primary);
    width: 22px;
    height: 22px;
    border-radius: var(--radius-pill);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}
.cal-bill-tag {
    font-size: 0.72rem;
    padding: 3px 6px;
    border-radius: 4px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    line-height: 1.2;
    cursor: pointer;
}
.cal-bill-tag.unpaid {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.cal-bill-tag.paid {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
</style>

<!-- Controls & Navigation -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="bill_calendar.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">
                &larr; Prev
            </a>
            <h2 style="font-size: 1.3rem; margin: 0; min-width: 160px; text-align: center;">
                <?= $monthLabel ?>
            </h2>
            <a href="bill_calendar.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">
                Next &rarr;
            </a>
            <a href="bill_calendar.php" class="btn btn-outline btn-sm" style="margin-left: 8px;">Today</a>
        </div>

        <div style="display: flex; align-items: center; gap: 20px;">
            <div>
                <span style="font-size: 0.76rem; color: var(--text-muted); text-transform: uppercase;">Unpaid Due:</span>
                <strong style="color: var(--warning); margin-left: 4px;"><?= formatCurrency($totalDueThisMonth, $currency) ?></strong>
            </div>
            <div>
                <span style="font-size: 0.76rem; color: var(--text-muted); text-transform: uppercase;">Paid:</span>
                <strong style="color: var(--success); margin-left: 4px;"><?= formatCurrency($totalPaidThisMonth, $currency) ?></strong>
            </div>
            <a href="bills.php" class="btn btn-primary btn-sm">
                <i data-lucide="list" style="width: 14px; height: 14px;"></i> List View
            </a>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card">
    <div class="calendar-grid">
        <!-- Day headers (Sun - Sat) -->
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>

        <!-- Empty cells preceding day 1 -->
        <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>

        <!-- Day cells (1 to daysInMonth) -->
        <?php 
        $todayDay = (int) date('j');
        $todayMonth = (int) date('n');
        $todayYear = (int) date('Y');

        for ($day = 1; $day <= $daysInMonth; $day++): 
            $isToday = ($day === $todayDay && $month === $todayMonth && $year === $todayYear);
            $dayBills = $billsByDay[$day] ?? [];
        ?>
            <div class="calendar-cell <?= $isToday ? 'today' : '' ?>">
                <div class="cell-day-num <?= $isToday ? 'today' : '' ?>"><?= $day ?></div>

                <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 4px;">
                    <?php foreach ($dayBills as $b): ?>
                        <div class="cal-bill-tag <?= $b['is_paid'] ? 'paid' : 'unpaid' ?>"
                             <?php if (!$b['is_paid']): ?>
                                 onclick="openPayBillModal(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['name'])) ?>', <?= (float)$b['amount'] ?>, '<?= $b['date'] ?>')"
                                 title="Click to Record Payment"
                             <?php else: ?>
                                 title="Paid: <?= formatCurrency($b['amount_paid'] ?: $b['amount'], $currency) ?> on <?= formatDate($b['paid_date']) ?>"
                             <?php endif; ?>>
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 75px; font-weight: 600;">
                                <?= sanitize($b['name']) ?>
                            </span>
                            <span style="font-weight: 700;">
                                <?= $currency ?><?= number_format((float)$b['amount'], 0) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
