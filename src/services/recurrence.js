/**
 * SmartSpend Recurrence Engine
 * Handles calendar intervals, month-end date preservation (e.g. 31st), and urgency statuses.
 */

export function calculateNextDueDate(currentDueDate, frequency, targetDueDay = null) {
  const dt = new Date(currentDueDate + 'T00:00:00');
  const day = targetDueDay !== null ? Number(targetDueDay) : dt.getDate();

  if (frequency === 'weekly') {
    dt.setDate(dt.getDate() + 7);
    return dt.toISOString().split('T')[0];
  }

  const monthsToAdd = {
    monthly: 1,
    bi_monthly: 2,
    quarterly: 3,
    half_yearly: 6,
    yearly: 12
  }[frequency] || 1;

  let nextYear = dt.getFullYear();
  let nextMonth = dt.getMonth() + monthsToAdd;

  nextYear += Math.floor(nextMonth / 12);
  nextMonth = nextMonth % 12;

  // Max days in destination month
  const maxDays = new Date(nextYear, nextMonth + 1, 0).getDate();
  const actualDay = Math.min(day, maxDays);

  const mStr = String(nextMonth + 1).padStart(2, '0');
  const dStr = String(actualDay).padStart(2, '0');
  return `${nextYear}-${mStr}-${dStr}`;
}

export function getDueStatus(dueDateStr) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const due = new Date(dueDateStr + 'T00:00:00');
  due.setHours(0, 0, 0, 0);

  const diffTime = due.getTime() - today.getTime();
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays < 0) {
    const abs = Math.abs(diffDays);
    return {
      text: `Overdue by ${abs} ${abs === 1 ? 'day' : 'days'}`,
      className: 'badge-danger',
      isOverdue: true,
      days: diffDays
    };
  } else if (diffDays === 0) {
    return {
      text: 'Due today',
      className: 'badge-warning',
      isDueToday: true,
      days: 0
    };
  } else if (diffDays === 1) {
    return {
      text: 'Due tomorrow',
      className: 'badge-warning',
      days: 1
    };
  } else if (diffDays <= 7) {
    return {
      text: `Due in ${diffDays} days`,
      className: 'badge-warning',
      days: diffDays
    };
  } else {
    const formatted = due.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
    return {
      text: `Due on ${formatted}`,
      className: 'badge-neutral',
      days: diffDays
    };
  }
}
