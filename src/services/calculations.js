/**
 * Financial Calculations & Aggregations Service
 */

export function formatCurrency(amount, currency = '₹') {
  const val = Number(amount) || 0;
  const formatted = Math.abs(val).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  return val < 0 ? `-${currency}${formatted}` : `${currency}${formatted}`;
}

export function resolvePeriodDates(period = 'this_month') {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();

  if (period === 'last_month') {
    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const start = new Date(prevYear, prevMonth, 1);
    const end = new Date(prevYear, prevMonth + 1, 0);
    return {
      startDate: start.toISOString().split('T')[0],
      endDate: end.toISOString().split('T')[0],
      label: start.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })
    };
  }

  if (period === 'last_30_days') {
    const start = new Date(now);
    start.setDate(start.getDate() - 30);
    return {
      startDate: start.toISOString().split('T')[0],
      endDate: now.toISOString().split('T')[0],
      label: 'Last 30 Days'
    };
  }

  if (period === 'year_to_date') {
    const start = new Date(year, 0, 1);
    return {
      startDate: start.toISOString().split('T')[0],
      endDate: now.toISOString().split('T')[0],
      label: `Year ${year}`
    };
  }

  // default 'this_month'
  const start = new Date(year, month, 1);
  const end = new Date(year, month + 1, 0);
  return {
    startDate: start.toISOString().split('T')[0],
    endDate: end.toISOString().split('T')[0],
    label: now.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })
  };
}

export function filterTransactionsByPeriod(transactions, startDate, endDate) {
  return transactions.filter(t => t.date >= startDate && t.date <= endDate);
}

export function calculateCashFlow(transactions) {
  let income = 0;
  let expenses = 0;

  for (const t of transactions) {
    const amt = Number(t.amount) || 0;
    if (t.type === 'income') {
      income += amt;
    } else {
      expenses += amt;
    }
  }

  return {
    income: Math.round(income * 100) / 100,
    expenses: Math.round(expenses * 100) / 100,
    net: Math.round((income - expenses) * 100) / 100
  };
}

export function getAccountBalance(openingBalance, allTransactions) {
  const { net } = calculateCashFlow(allTransactions);
  return Math.round((Number(openingBalance || 0) + net) * 100) / 100;
}

export function getCategoryExpenseBreakdown(transactions, categories) {
  const catMap = {};
  for (const c of categories) {
    if (c.type === 'expense') {
      catMap[c.id] = {
        id: c.id,
        name: c.name,
        color: c.color,
        icon: c.icon,
        is_essential: c.is_essential,
        total: 0,
        count: 0
      };
    }
  }

  for (const t of transactions) {
    if (t.type === 'expense' && catMap[t.category_id]) {
      catMap[t.category_id].total += Number(t.amount) || 0;
      catMap[t.category_id].count += 1;
    }
  }

  return Object.values(catMap).filter(c => c.total > 0).sort((a, b) => b.total - a.total);
}

export function getSixMonthTrajectory(allTransactions) {
  const result = [];
  const now = new Date();

  for (let i = 5; i >= 0; i--) {
    const dt = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const startStr = dt.toISOString().split('T')[0];
    const endStr = new Date(dt.getFullYear(), dt.getMonth() + 1, 0).toISOString().split('T')[0];
    const label = dt.toLocaleDateString('en-IN', { month: 'short' });

    const monthTxns = filterTransactionsByPeriod(allTransactions, startStr, endStr);
    const { income, expenses } = calculateCashFlow(monthTxns);

    result.push({
      month: label,
      income,
      expenses,
      net: income - expenses
    });
  }

  return result;
}
