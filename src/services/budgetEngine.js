/**
 * Smart Salary-Based Money Management & Budget Engine
 * Provides mathematical capacity planning, flexible spending calculations,
 * and the "Can I Afford This?" purchase evaluation engine.
 */

import { formatCurrency } from './calculations';

export function getMonthlyCapacityPlan({
  monthlyIncome,
  transactionsThisMonth,
  recurringBills,
  categoryBudgets,
  categories,
  monthlySavingsTarget,
  savingsStyle = 'balanced'
}) {
  const expectedIncome = Number(monthlyIncome) || 0;
  
  // Calculate actual income received this month
  const actualIncomeReceived = transactionsThisMonth
    .filter(t => t.type === 'income')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0);

  const effectiveIncome = expectedIncome > 0 ? expectedIncome : actualIncomeReceived;

  // 1. Essential Category Budgets
  const essentialCatIds = new Set(
    categories.filter(c => c.type === 'expense' && c.is_essential).map(c => c.id)
  );

  let essentialBudgetTotal = 0;
  for (const b of categoryBudgets) {
    if (essentialCatIds.has(b.category_id)) {
      essentialBudgetTotal += Number(b.amount || 0);
    }
  }

  // 2. Unpaid Recurring Bills due this month
  // Exclude bills belonging to an essential category that already has a budget to prevent double-counting!
  const todayStr = new Date().toISOString().split('T')[0];
  const endOfMonthStr = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0)
    .toISOString()
    .split('T')[0];

  const budgetedCategoryIds = new Set(categoryBudgets.filter(b => Number(b.amount) > 0).map(b => b.category_id));

  let unpaidSeparateBills = 0;
  const unpaidBillsList = [];

  for (const bill of recurringBills) {
    if (bill.status === 'active' && bill.next_due_date >= todayStr && bill.next_due_date <= endOfMonthStr) {
      if (!budgetedCategoryIds.has(bill.category_id)) {
        unpaidSeparateBills += Number(bill.amount || 0);
        unpaidBillsList.push(bill);
      }
    }
  }

  // 3. Planned Savings Target
  let plannedSavings = Number(monthlySavingsTarget) || 0;
  if (plannedSavings <= 0 && effectiveIncome > 0) {
    const rate = savingsStyle === 'conservative' ? 0.10 : (savingsStyle === 'ambitious' ? 0.30 : 0.20);
    plannedSavings = Math.round(effectiveIncome * rate);
  }

  // 4. Discretionary spending already spent this month
  const discretionarySpent = transactionsThisMonth
    .filter(t => t.type === 'expense' && !essentialCatIds.has(t.category_id))
    .reduce((sum, t) => sum + Number(t.amount || 0), 0);

  // 5. Projected Flexible Spending Formula:
  // Projected Flexible = Expected Income - Essential Budget - Unpaid Separate Bills - Planned Savings
  const projectedFlexibleTotal = Math.max(0, effectiveIncome - essentialBudgetTotal - unpaidSeparateBills - plannedSavings);
  const remainingFlexible = Math.max(0, projectedFlexibleTotal - discretionarySpent);

  // 6. Remaining Days in Month & Daily Discretionary Allowance
  const now = new Date();
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const remainingDays = Math.max(1, daysInMonth - now.getDate() + 1);
  const dailyDiscretionary = Math.round((remainingFlexible / remainingDays) * 100) / 100;

  // 7. Overall budget usage
  const totalBudgeted = categoryBudgets.reduce((s, b) => s + Number(b.amount || 0), 0);
  const totalSpent = transactionsThisMonth
    .filter(t => t.type === 'expense')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0);
  const budgetUsagePercent = totalBudgeted > 0
    ? Math.round((totalSpent / totalBudgeted) * 100)
    : (effectiveIncome > 0 ? Math.round((totalSpent / effectiveIncome) * 100) : 0);

  return {
    expectedIncome,
    actualIncomeReceived,
    effectiveIncome,
    essentialBudgetTotal,
    unpaidSeparateBills,
    unpaidBillsList,
    plannedSavings,
    discretionarySpent,
    projectedFlexibleTotal,
    remainingFlexible,
    remainingDays,
    dailyDiscretionary,
    totalBudgeted,
    totalSpent,
    budgetUsagePercent
  };
}

/**
 * "Can I Afford This?" Purchase Calculator Evaluator
 */
export function evaluatePurchaseAffordability({
  purchaseName,
  price,
  isEssential = false,
  accountBalance,
  upcomingBillsTotal,
  remainingFlexible,
  plannedSavingsTarget,
  dailyDiscretionary,
  remainingDays,
  currency = '₹'
}) {
  const pPrice = Number(price) || 0;
  const balance = Number(accountBalance) || 0;
  const unpaidBills = Number(upcomingBillsTotal) || 0;
  const flexible = Number(remainingFlexible) || 0;
  const savings = Number(plannedSavingsTarget) || 0;

  let status = 'affordable'; // 'affordable', 'tight', 'risky'
  let verdict = '';
  const reasons = [];
  const alternatives = [];

  const postPurchaseBalance = balance - pPrice;
  const violatesBillReserve = postPurchaseBalance < unpaidBills && balance > 0;
  const violatesFlexible = pPrice > flexible;
  const exceedsTotalCash = pPrice > balance && balance > 0;

  if (exceedsTotalCash || violatesBillReserve) {
    status = 'risky';
    verdict = `Not Recommended Right Now: Making this purchase will leave insufficient funds for your scheduled unpaid bills or trigger an account deficit.`;
    if (exceedsTotalCash) {
      reasons.push(`The price (${formatCurrency(pPrice, currency)}) exceeds your total liquid bank balance (${formatCurrency(balance, currency)}).`);
    }
    if (violatesBillReserve) {
      reasons.push(`You have ${formatCurrency(unpaidBills, currency)} in unpaid bills coming up this month. Purchasing this now leaves only ${formatCurrency(postPurchaseBalance, currency)}, which cannot cover those bills.`);
    }
    if (violatesFlexible) {
      reasons.push(`This purchase exceeds your remaining flexible spending allowance of ${formatCurrency(flexible, currency)} by ${formatCurrency(pPrice - flexible, currency)}.`);
    }
  } else if (violatesFlexible) {
    status = 'tight';
    const shortfall = pPrice - flexible;
    verdict = `Affordable with Compromise: You have sufficient cash in the bank, but this purchase exceeds your monthly discretionary allowance and will require dipping into your savings goal.`;
    reasons.push(`This purchase exceeds your remaining monthly flexible budget of ${formatCurrency(flexible, currency)}.`);
    reasons.push(`To buy this now, you will need to redirect ${formatCurrency(shortfall, currency)} from your monthly savings target of ${formatCurrency(savings, currency)}.`);
  } else {
    status = 'affordable';
    const postPurchaseFlexible = flexible - pPrice;
    verdict = `Comfortably Affordable! You have sufficient cash and flexible spending allowance to make this purchase without harming upcoming bills or your savings target.`;
    reasons.push(`Your remaining flexible budget after this purchase will still be ${formatCurrency(postPurchaseFlexible, currency)}.`);
    reasons.push(`All ${formatCurrency(unpaidBills, currency)} in upcoming bills remain 100% funded.`);
  }

  // Daily allowance impact
  const newFlexible = Math.max(0, flexible - pPrice);
  const newDaily = Math.round((newFlexible / Math.max(1, remainingDays)) * 100) / 100;

  // Practical Alternatives
  if (status !== 'affordable') {
    const threeMonth = Math.round((pPrice / 3) * 100) / 100;
    alternatives.push({
      title: 'Create a 3-Month Dedicated Savings Goal',
      description: `Save ${formatCurrency(threeMonth, currency)} per month for 3 months to purchase "${purchaseName}" without disturbing your living budget.`
    });
    alternatives.push({
      title: 'Wait Until Next Salary Credit',
      description: `Postpone until your next salary deposit when your flexible budget resets for the new cycle.`
    });
    alternatives.push({
      title: 'Pause Discretionary Spending',
      description: `Pause dining out, shopping, and entertainment for the remainder of this month to protect your cash reserves.`
    });
  }

  return {
    purchaseName,
    price: pPrice,
    isEssential,
    status,
    verdict,
    reasons,
    alternatives,
    impacts: {
      currentDaily: dailyDiscretionary,
      newDaily: newDaily,
      remainingFlexibleAfter: newFlexible
    },
    disclaimer: 'Note: This evaluation is a deterministic financial projection based on your recorded cash flow and scheduled bills. It does not constitute formal banking advice.'
  };
}

export function generateRecommendedBudgets(monthlyIncome, categories) {
  const income = Number(monthlyIncome) || 45000;
  const ratios = {
    'Housing & Rent': 0.28,
    'Food & Groceries': 0.18,
    'Electricity & Utilities': 0.06,
    'Transportation': 0.08,
    'Healthcare & Medical': 0.05,
    'Entertainment & Dining': 0.08,
    'Shopping & Lifestyle': 0.07,
    'Subscriptions & Media': 0.03,
    'Personal Care': 0.04,
    'Other Expense': 0.03
  };

  const results = [];
  for (const c of categories) {
    if (c.type === 'expense') {
      const ratio = ratios[c.name] || 0.03;
      results.push({
        category_id: c.id,
        category_name: c.name,
        amount: Math.round((income * ratio) / 100) * 100
      });
    }
  }
  return results;
}
