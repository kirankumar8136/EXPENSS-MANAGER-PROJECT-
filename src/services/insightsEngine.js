/**
 * Financial Insights & Behavioral Spending Analysis Service
 * Generates transparent, deterministic, rule-based recommendations.
 */

import { formatCurrency } from './calculations';

export function generateFinancialInsights({
  allTransactions,
  categories,
  recurringBills,
  capacityPlan,
  currency = '₹'
}) {
  const insights = [];
  const now = new Date();
  const dayOfMonth = now.getDate();

  // 1. Discretionary Burn Rate Alert
  if (capacityPlan.remainingFlexible <= 0 && capacityPlan.remainingDays > 2) {
    insights.push({
      type: 'danger',
      icon: 'AlertTriangle',
      title: 'Flexible Spending Allowance Depleted',
      description: `You have consumed 100% of your projected flexible spending budget with ${capacityPlan.remainingDays} days remaining in the month. Additional non-essential purchases will cut into your savings or bill reserves.`,
      actionText: 'Review your budget allocations',
      actionView: 'smart_budget'
    });
  } else if (capacityPlan.dailyDiscretionary > 0) {
    insights.push({
      type: 'info',
      icon: 'Compass',
      title: 'Safe Daily Spending Allowance',
      description: `Based on your remaining flexible budget of ${formatCurrency(capacityPlan.remainingFlexible, currency)}, you can safely spend up to ${formatCurrency(capacityPlan.dailyDiscretionary, currency)} per day for the remaining ${capacityPlan.remainingDays} days.`,
      actionText: 'Maintain this pace to hit your monthly savings goal',
      actionView: 'smart_budget'
    });
  }

  // 2. Category Spending Surges (Apples-to-Apples Period Comparison)
  // Compare 1st to Day N of current month with 1st to Day N of previous month
  const curYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const prevYm = `${prevMonthDate.getFullYear()}-${String(prevMonthDate.getMonth() + 1).padStart(2, '0')}`;

  const curDayStr = `${curYm}-${String(dayOfMonth).padStart(2, '0')}`;
  const prevDayStr = `${prevYm}-${String(dayOfMonth).padStart(2, '0')}`;

  const catMap = {};
  for (const c of categories) {
    if (c.type === 'expense') {
      catMap[c.id] = { id: c.id, name: c.name, cur: 0, prev: 0 };
    }
  }

  for (const t of allTransactions) {
    if (t.type === 'expense' && catMap[t.category_id]) {
      if (t.date >= `${curYm}-01` && t.date <= curDayStr) {
        catMap[t.category_id].cur += Number(t.amount || 0);
      } else if (t.date >= `${prevYm}-01` && t.date <= prevDayStr) {
        catMap[t.category_id].prev += Number(t.amount || 0);
      }
    }
  }

  for (const c of Object.values(catMap)) {
    if (c.prev > 300 && c.cur > c.prev) {
      const diff = c.cur - c.prev;
      const pct = Math.round((diff / c.prev) * 100);
      if (pct >= 20 && diff >= 400) {
        insights.push({
          type: 'warning',
          icon: 'TrendingUp',
          title: `Surge in ${c.name} Spending`,
          description: `Your ${c.name} spending is up by ${pct}% (+${formatCurrency(diff, currency)}) compared with the first ${dayOfMonth} days of last month (${formatCurrency(c.cur, currency)} vs ${formatCurrency(c.prev, currency)}).`,
          actionText: `Filter transactions in ${c.name}`,
          actionView: 'transactions'
        });
      }
    }
  }

  // 3. Subscriptions Burden Analysis
  const subs = recurringBills.filter(
    b => b.status === 'active' &&
    (b.name.toLowerCase().includes('netflix') ||
     b.name.toLowerCase().includes('spotify') ||
     b.name.toLowerCase().includes('prime') ||
     b.name.toLowerCase().includes('subscription') ||
     b.name.toLowerCase().includes('hotstar') ||
     b.name.toLowerCase().includes('gym'))
  );

  const subTotal = subs.reduce((sum, s) => sum + Number(s.amount || 0), 0);
  if (subs.length >= 2 && subTotal > 0) {
    const annual = subTotal * 12;
    insights.push({
      type: 'info',
      icon: 'Tv',
      title: `Recurring Subscriptions Audit (${subs.length} Active)`,
      description: `You have ${subs.length} active recurring subscriptions totaling ${formatCurrency(subTotal, currency)}/month (${formatCurrency(annual, currency)} per year). Reviewing unused memberships can yield immediate monthly savings.`,
      actionText: 'Manage Recurring Bills',
      actionView: 'bills'
    });
  }

  return insights;
}
