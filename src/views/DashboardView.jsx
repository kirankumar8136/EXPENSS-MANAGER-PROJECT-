import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import {
  resolvePeriodDates,
  filterTransactionsByPeriod,
  calculateCashFlow,
  getAccountBalance,
  getCategoryExpenseBreakdown,
  getSixMonthTrajectory,
  formatCurrency
} from '../services/calculations';
import { getMonthlyCapacityPlan } from '../services/budgetEngine';
import { generateFinancialInsights } from '../services/insightsEngine';
import { getDueStatus } from '../services/recurrence';

import {
  TrendingUp,
  TrendingDown,
  Wallet,
  Target,
  PiggyBank,
  Compass,
  PieChart as PieIcon,
  LineChart as LineIcon,
  BarChart2,
  Scale,
  Clock,
  CalendarClock,
  Sparkles,
  Plus,
  HelpCircle,
  FileText,
  AlertTriangle,
  Tv,
  ArrowRight
} from 'lucide-react';

import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line, Doughnut, Bar } from 'react-chartjs-2';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

// Chart.js dark defaults
ChartJS.defaults.color = '#94a3b8';
ChartJS.defaults.font.family = "'Inter', system-ui, sans-serif";

export default function DashboardView() {
  const {
    transactions,
    settings,
    openingBalance,
    recurringBills,
    budgets,
    categories,
    savingsGoals,
    user,
    setActiveView,
    setIsQuickTxnOpen,
    setIsAffordModalOpen,
    setPayBillData
  } = useApp();

  const [period, setPeriod] = useState('this_month');
  const currency = user?.currency || '₹';

  // 1. Resolve selected period
  const { startDate, endDate, label: periodLabel } = resolvePeriodDates(period);

  // 2. Filter transactions for period
  const periodTxns = filterTransactionsByPeriod(transactions, startDate, endDate);
  const { income: totalIncome, expenses: totalExpenses, net: netFlow } = calculateCashFlow(periodTxns);

  // 3. Current liquid account balance
  const accountBalance = getAccountBalance(openingBalance, transactions);

  // 4. Capacity planning & flexible budget
  const capacity = getMonthlyCapacityPlan({
    monthlyIncome: settings.monthly_income,
    transactionsThisMonth: periodTxns,
    recurringBills,
    categoryBudgets: budgets,
    categories,
    monthlySavingsTarget: settings.monthly_savings_target,
    savingsStyle: settings.savings_style
  });

  // 5. Actual savings contributions in period
  let actualSavings = 0;
  for (const g of savingsGoals) {
    if (g.contributions) {
      for (const c of g.contributions) {
        if (c.date >= startDate && c.date <= endDate) {
          actualSavings += c.type === 'deposit' ? Number(c.amount) : -Number(c.amount);
        }
      }
    }
  }
  actualSavings = Math.max(0, actualSavings);

  // 6. Upcoming unpaid bills
  const todayStr = new Date().toISOString().split('T')[0];
  const upcomingBills = recurringBills
    .filter((b) => b.status === 'active' && b.next_due_date >= todayStr && b.next_due_date <= endDate)
    .sort((a, b) => a.next_due_date.localeCompare(b.next_due_date));
  const upcomingBillsTotal = upcomingBills.reduce((s, b) => s + Number(b.amount || 0), 0);

  // 7. Chart data
  const categoryExpenses = getCategoryExpenseBreakdown(periodTxns, categories);
  const trajectory = getSixMonthTrajectory(transactions);

  // Category Budgets vs Actual
  const budgetVsActualData = categories
    .filter((c) => c.type === 'expense')
    .map((c) => {
      const bObj = budgets.find((b) => b.category_id === c.id);
      const spent = periodTxns
        .filter((t) => t.category_id === c.id && t.type === 'expense')
        .reduce((sum, t) => sum + Number(t.amount || 0), 0);
      return {
        name: c.name,
        budget: bObj ? Number(bObj.amount) : 0,
        spent
      };
    })
    .filter((c) => c.budget > 0 || c.spent > 0)
    .slice(0, 8);

  // 8. Financial Insights
  const insights = generateFinancialInsights({
    allTransactions: transactions,
    categories,
    recurringBills,
    capacityPlan: capacity,
    currency
  });

  // Chart configs
  const trendChartData = {
    labels: trajectory.map((t) => t.month),
    datasets: [
      {
        label: 'Income',
        data: trajectory.map((t) => t.income),
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        fill: true,
        tension: 0.35,
        pointRadius: 4
      },
      {
        label: 'Expenses',
        data: trajectory.map((t) => t.expenses),
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        fill: true,
        tension: 0.35,
        pointRadius: 4
      }
    ]
  };

  const doughnutData = {
    labels: categoryExpenses.map((c) => c.name),
    datasets: [
      {
        data: categoryExpenses.map((c) => c.total),
        backgroundColor: categoryExpenses.map((c) => c.color || '#6366f1'),
        borderWidth: 2,
        borderColor: '#182234',
        hoverOffset: 4
      }
    ]
  };

  const cashFlowBarData = {
    labels: ['Total Earned', 'Total Spent'],
    datasets: [
      {
        data: [totalIncome, totalExpenses],
        backgroundColor: ['#10b981', '#ef4444'],
        borderRadius: 8,
        barPercentage: 0.5
      }
    ]
  };

  const budgetBarData = {
    labels: budgetVsActualData.map((b) => b.name),
    datasets: [
      {
        label: 'Budget Limit',
        data: budgetVsActualData.map((b) => b.budget),
        backgroundColor: 'rgba(99, 102, 241, 0.6)',
        borderRadius: 6
      },
      {
        label: 'Actual Spent',
        data: budgetVsActualData.map((b) => b.spent),
        backgroundColor: 'rgba(239, 68, 68, 0.7)',
        borderRadius: 6
      }
    ]
  };

  return (
    <div>
      {/* Controls Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 24 }}>
        <div className="period-selector">
          <button className={`period-btn ${period === 'this_month' ? 'active' : ''}`} onClick={() => setPeriod('this_month')}>
            This Month
          </button>
          <button className={`period-btn ${period === 'last_month' ? 'active' : ''}`} onClick={() => setPeriod('last_month')}>
            Last Month
          </button>
          <button className={`period-btn ${period === 'last_30_days' ? 'active' : ''}`} onClick={() => setPeriod('last_30_days')}>
            Last 30 Days
          </button>
          <button className={`period-btn ${period === 'year_to_date' ? 'active' : ''}`} onClick={() => setPeriod('year_to_date')}>
            Year to Date
          </button>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
          <button className="btn btn-outline btn-sm" onClick={() => setIsQuickTxnOpen(true)}>
            <Plus size={14} /> Add Transaction
          </button>
          <button className="btn btn-outline btn-sm" onClick={() => setIsAffordModalOpen(true)}>
            <HelpCircle size={14} /> Can I Afford This?
          </button>
          <button className="btn btn-outline btn-sm" onClick={() => setActiveView('reports')}>
            <FileText size={14} /> View Reports
          </button>
        </div>
      </div>

      {/* 8 Financial Summary KPI Cards */}
      <div className="kpi-grid">
        {/* 1. Total Income */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Total Income</span>
            <div className="kpi-icon income"><TrendingUp size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(totalIncome, currency)}</div>
          <div className="kpi-subtext">Period: {periodLabel}</div>
        </div>

        {/* 2. Total Expenses */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Total Expenses</span>
            <div className="kpi-icon expense"><TrendingDown size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(totalExpenses, currency)}</div>
          <div className="kpi-subtext">Net Flow: {formatCurrency(netFlow, currency)}</div>
        </div>

        {/* 3. Account Cash Balance */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Account Balance</span>
            <div className="kpi-icon balance"><Wallet size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(accountBalance, currency)}</div>
          <div className="kpi-subtext">Liquid cash & bank reserve</div>
        </div>

        {/* 4. Planned Savings Target */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Savings Target</span>
            <div className="kpi-icon savings"><Target size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(capacity.plannedSavings, currency)}</div>
          <div className="kpi-subtext">Monthly wealth accumulation</div>
        </div>

        {/* 5. Actual Savings */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Actual Savings</span>
            <div className="kpi-icon savings"><PiggyBank size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(actualSavings, currency)}</div>
          <div className="kpi-subtext">
            {capacity.plannedSavings > 0
              ? `${Math.round((actualSavings / capacity.plannedSavings) * 100)}% of target saved`
              : 'Goal contributions'}
          </div>
        </div>

        {/* 6. Upcoming Bills */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Upcoming Bills</span>
            <div className="kpi-icon bills"><CalendarClock size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(upcomingBillsTotal, currency)}</div>
          <div className="kpi-subtext">Unpaid commitments this cycle</div>
        </div>

        {/* 7. Flexible Budget */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Flexible Budget</span>
            <div className="kpi-icon flexible"><Compass size={18} /></div>
          </div>
          <div className="kpi-value">{formatCurrency(capacity.remainingFlexible, currency)}</div>
          <div className="kpi-subtext">
            Safe Daily: {formatCurrency(capacity.dailyDiscretionary, currency)}/day
          </div>
        </div>

        {/* 8. Budget Utilized */}
        <div className="kpi-card">
          <div className="kpi-header">
            <span className="kpi-title">Budget Utilized</span>
            <div className="kpi-icon budget"><PieIcon size={18} /></div>
          </div>
          <div className="kpi-value">{capacity.budgetUsagePercent}%</div>
          <div className="kpi-subtext">
            {capacity.budgetUsagePercent > 100 ? 'Exceeded Limit' : 'On Healthy Track'}
          </div>
        </div>
      </div>

      {/* Charts Grid Main */}
      <div className="charts-grid-main">
        {/* Trajectory */}
        <div className="chart-card">
          <div className="card-header">
            <div>
              <h3 className="card-title"><LineIcon size={18} style={{ color: 'var(--primary)' }} /> 6-Month Income vs. Expense Trajectory</h3>
              <div className="card-subtitle">Cash flow trajectory over the past 6 months</div>
            </div>
          </div>
          <div className="chart-card-body">
            <Line
              data={trendChartData}
              options={{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: { position: 'top', labels: { color: '#94a3b8', boxWidth: 12 } }
                },
                scales: {
                  x: { grid: { display: false } },
                  y: { grid: { color: 'rgba(255, 255, 255, 0.05)' } }
                }
              }}
            />
          </div>
        </div>

        {/* Category Doughnut */}
        <div className="chart-card">
          <div className="card-header">
            <div>
              <h3 className="card-title"><PieIcon size={18} style={{ color: '#f59e0b' }} /> Category Breakdown</h3>
              <div className="card-subtitle">Spending distribution in {periodLabel}</div>
            </div>
          </div>
          <div className="chart-card-body">
            {categoryExpenses.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state-title">No Expenses in Period</div>
                <div className="empty-state-text">Record an expense to view category distribution.</div>
              </div>
            ) : (
              <Doughnut
                data={doughnutData}
                options={{
                  responsive: true,
                  maintainAspectRatio: false,
                  cutout: '70%',
                  plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 10, padding: 8 } }
                  }
                }}
              />
            )}
          </div>
        </div>
      </div>

      {/* Secondary Charts Grid */}
      <div className="charts-grid-secondary">
        {/* Cash Flow Bar */}
        <div className="chart-card">
          <div className="card-header">
            <div>
              <h3 className="card-title"><BarChart2 size={18} style={{ color: 'var(--success)' }} /> Period Cash Flow</h3>
              <div className="card-subtitle">Total Income vs Total Outflow</div>
            </div>
          </div>
          <div className="chart-card-body">
            <Bar
              data={cashFlowBarData}
              options={{
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                  x: { grid: { display: false } },
                  y: { grid: { color: 'rgba(255, 255, 255, 0.05)' } }
                }
              }}
            />
          </div>
        </div>

        {/* Budget vs Actual */}
        <div className="chart-card">
          <div className="card-header">
            <div>
              <h3 className="card-title"><Scale size={18} style={{ color: '#8b5cf6' }} /> Budget vs. Actual</h3>
              <div className="card-subtitle">Planned limit vs actual expense per category</div>
            </div>
            <button className="btn btn-outline btn-sm" onClick={() => setActiveView('smart_budget')}>
              Manage
            </button>
          </div>
          <div className="chart-card-body">
            {budgetVsActualData.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state-title">No Budgets Defined</div>
                <button className="btn btn-outline btn-sm" onClick={() => setActiveView('smart_budget')}>
                  Set Category Limits
                </button>
              </div>
            ) : (
              <Bar
                data={budgetBarData}
                options={{
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: { legend: { position: 'top', labels: { color: '#94a3b8', boxWidth: 10 } } },
                  scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 0 } },
                    y: { grid: { color: 'rgba(255, 255, 255, 0.05)' } }
                  }
                }}
              />
            )}
          </div>
        </div>
      </div>

      {/* Widgets Grid: Recent Transactions & Upcoming Bills */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, marginBottom: 28 }}>
        {/* Recent Transactions Widget */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title"><Clock size={18} style={{ color: 'var(--primary)' }} /> Recent Transactions</h3>
            <button className="btn btn-outline btn-sm" onClick={() => setActiveView('transactions')}>
              View All &rarr;
            </button>
          </div>

          {transactions.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state-title">No Transactions Recorded</div>
              <button className="btn btn-primary btn-sm" onClick={() => setIsQuickTxnOpen(true)}>
                Add Transaction
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              {transactions.slice(0, 5).map((txn) => {
                const cat = categories.find((c) => c.id === txn.category_id);
                return (
                  <div
                    key={txn.id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      padding: '12px 0',
                      borderBottom: '1px solid var(--border-color)'
                    }}
                  >
                    <div>
                      <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.9rem' }}>
                        {txn.description}
                      </div>
                      <div style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                        {cat?.name || 'Category'} • {txn.date}
                      </div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div
                        style={{
                          fontWeight: 700,
                          fontSize: '0.95rem',
                          color: txn.type === 'income' ? 'var(--success)' : 'var(--text-primary)'
                        }}
                      >
                        {txn.type === 'income' ? '+' : '-'}
                        {formatCurrency(txn.amount, currency)}
                      </div>
                      <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
                        {txn.payment_method?.toUpperCase()}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Upcoming Bills Widget */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title"><CalendarClock size={18} style={{ color: 'var(--warning)' }} /> Upcoming Bills & Deadlines</h3>
            <button className="btn btn-outline btn-sm" onClick={() => setActiveView('bills')}>
              All Bills &rarr;
            </button>
          </div>

          {upcomingBills.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state-title">All Caught Up!</div>
              <div className="empty-state-text">No pending unpaid bills due in this period.</div>
              <button className="btn btn-outline btn-sm" onClick={() => setActiveView('bills')}>
                Manage Recurring Bills
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {upcomingBills.slice(0, 4).map((b) => {
                const dueStatus = getDueStatus(b.next_due_date);
                return (
                  <div
                    key={b.id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      padding: '12px 14px',
                      background: 'var(--bg-surface)',
                      border: '1px solid var(--border-color)',
                      borderRadius: 'var(--radius-md)'
                    }}
                  >
                    <div>
                      <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.9rem' }}>
                        {b.name}
                      </div>
                      <div style={{ fontSize: '0.76rem', color: dueStatus.isOverdue ? 'var(--danger)' : 'var(--warning)' }}>
                        {dueStatus.text}
                      </div>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '0.95rem' }}>
                        {formatCurrency(b.amount, currency)}
                      </div>
                      <button
                        className="btn btn-success btn-sm"
                        onClick={() =>
                          setPayBillData({
                            billId: b.id,
                            name: b.name,
                            amount: b.amount,
                            dueDate: b.next_due_date
                          })
                        }
                      >
                        Pay
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Smart Financial Insights Feed */}
      <div className="card" style={{ marginBottom: 28 }}>
        <div className="card-header">
          <h3 className="card-title"><Sparkles size={18} style={{ color: 'var(--primary)' }} /> Smart Financial Insights</h3>
          <button className="btn btn-outline btn-sm" onClick={() => setActiveView('insights')}>
            Deep Analysis &rarr;
          </button>
        </div>

        {insights.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-title">Finances Looking Healthy</div>
            <div className="empty-state-text">No spending surges or budget warnings detected for this period.</div>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {insights.slice(0, 3).map((insight, idx) => (
              <div
                key={idx}
                style={{
                  padding: 16,
                  borderRadius: 10,
                  borderLeft: `4px solid ${
                    insight.type === 'danger'
                      ? 'var(--danger)'
                      : insight.type === 'warning'
                      ? 'var(--warning)'
                      : 'var(--primary)'
                  }`,
                  backgroundColor: 'var(--bg-surface)',
                  display: 'flex',
                  gap: 14
                }}
              >
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: '0.92rem', fontWeight: 600, color: 'var(--text-primary)', marginBottom: 4 }}>
                    {insight.title}
                  </div>
                  <div style={{ fontSize: '0.82rem', color: 'var(--text-secondary)', lineHeight: 1.4, marginBottom: 8 }}>
                    {insight.description}
                  </div>
                  {insight.actionText && (
                    <button
                      onClick={() => setActiveView(insight.actionView || 'dashboard')}
                      style={{
                        background: 'none',
                        border: 'none',
                        color: 'var(--primary)',
                        fontSize: '0.78rem',
                        fontWeight: 600,
                        cursor: 'pointer',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 4
                      }}
                    >
                      <span>{insight.actionText}</span>
                      <ArrowRight size={13} />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
