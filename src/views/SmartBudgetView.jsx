import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { getMonthlyCapacityPlan } from '../services/budgetEngine';
import { formatCurrency } from '../services/calculations';
import { Calculator, Sparkles, Layers, Edit } from 'lucide-react';

export default function SmartBudgetView() {
  const {
    transactions,
    settings,
    recurringBills,
    budgets,
    categories,
    user,
    setCategoryBudget,
    applyRecommendedBudgets
  } = useApp();

  const [editingBudget, setEditingBudget] = useState(null); // { categoryId, categoryName, amount }

  const currency = user?.currency || '₹';

  // Compute live current month parameters
  const now = new Date();
  const curYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const monthStart = `${curYm}-01`;
  const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

  const periodTxns = transactions.filter((t) => t.date >= monthStart && t.date <= monthEnd);

  const capacity = getMonthlyCapacityPlan({
    monthlyIncome: settings.monthly_income,
    transactionsThisMonth: periodTxns,
    recurringBills,
    categoryBudgets: budgets,
    categories,
    monthlySavingsTarget: settings.monthly_savings_target,
    savingsStyle: settings.savings_style
  });

  // Calculate actual spent per category
  const categoryRows = categories
    .filter((c) => c.type === 'expense')
    .map((c) => {
      const bObj = budgets.find((b) => b.category_id === c.id);
      const budgetAmount = bObj ? Number(bObj.amount) : 0;
      const spentAmount = periodTxns
        .filter((t) => t.category_id === c.id && t.type === 'expense')
        .reduce((sum, t) => sum + Number(t.amount || 0), 0);
      const remaining = budgetAmount - spentAmount;
      const percentUsed = budgetAmount > 0 ? Math.round((spentAmount / budgetAmount) * 100) : 0;

      return {
        id: c.id,
        name: c.name,
        color: c.color,
        is_essential: c.is_essential,
        budgetAmount,
        spentAmount,
        remaining,
        percentUsed
      };
    })
    .sort((a, b) => b.is_essential - a.is_essential || b.budgetAmount - a.budgetAmount);

  const handleSaveBudget = (e) => {
    e.preventDefault();
    if (!editingBudget) return;
    setCategoryBudget(editingBudget.categoryId, Number(editingBudget.amount));
    setEditingBudget(null);
  };

  return (
    <div>
      {/* Capacity Formula Banner */}
      <div
        className="card"
        style={{
          marginBottom: 24,
          background: 'linear-gradient(135deg, rgba(30, 27, 75, 0.6) 0%, var(--bg-card) 100%)'
        }}
      >
        <div className="card-header">
          <div>
            <h3 className="card-title" style={{ fontSize: '1.25rem' }}>
              <Calculator size={20} style={{ color: 'var(--primary)' }} />
              Salary-Based Money Capacity Formula
            </h3>
            <div className="card-subtitle">
              Transparent mathematical projection of your monthly discretionary capacity
            </div>
          </div>
          <div>
            <button
              className="btn btn-primary btn-sm"
              onClick={() => {
                if (window.confirm('Apply recommended standard budget allocations based on your income?')) {
                  applyRecommendedBudgets(capacity.effectiveIncome);
                }
              }}
            >
              <Sparkles size={14} /> Auto-Populate Recommended
            </button>
          </div>
        </div>

        {/* The Equation Bar */}
        <div
          style={{
            background: 'rgba(0,0,0,0.25)',
            border: '1px solid var(--border-color)',
            borderRadius: 12,
            padding: 18,
            marginBottom: 20
          }}
        >
          <div style={{ fontSize: '0.76rem', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 8, fontWeight: 700 }}>
            The SmartSpend Flexible Spending Equation:
          </div>
          <div style={{ fontFamily: 'monospace', fontSize: '0.95rem', color: '#f8fafc', lineHeight: 1.8 }}>
            <span style={{ color: 'var(--success)', fontWeight: 700 }}>
              Expected Income ({formatCurrency(capacity.effectiveIncome, currency)})
            </span>
            <span style={{ color: 'var(--text-muted)' }}> − </span>
            <span style={{ color: 'var(--danger)', fontWeight: 700 }}>
              Essential Costs ({formatCurrency(capacity.essentialBudgetTotal, currency)})
            </span>
            <span style={{ color: 'var(--text-muted)' }}> − </span>
            <span style={{ color: 'var(--warning)', fontWeight: 700 }}>
              Unpaid Separate Bills ({formatCurrency(capacity.unpaidSeparateBills, currency)})
            </span>
            <span style={{ color: 'var(--text-muted)' }}> − </span>
            <span style={{ color: '#8b5cf6', fontWeight: 700 }}>
              Planned Savings ({formatCurrency(capacity.plannedSavings, currency)})
            </span>
            <span style={{ color: 'var(--text-muted)' }}> = </span>
            <span style={{ color: '#38bdf8', fontWeight: 800, fontSize: '1.1rem' }}>
              Projected Flexible Spending: {formatCurrency(capacity.projectedFlexibleTotal, currency)}
            </span>
          </div>
        </div>

        {/* 4 KPI Cards */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 14 }}>
          <div style={{ padding: 14, background: 'var(--bg-surface)', border: '1px solid var(--border-color)', borderRadius: 10 }}>
            <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Remaining Flexible</div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#38bdf8', marginTop: 4 }}>
              {formatCurrency(capacity.remainingFlexible, currency)}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
              Discretionary spent: {formatCurrency(capacity.discretionarySpent, currency)}
            </div>
          </div>

          <div style={{ padding: 14, background: 'var(--bg-surface)', border: '1px solid var(--border-color)', borderRadius: 10 }}>
            <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Safe Daily Allowance</div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: 'var(--primary)', marginTop: 4 }}>
              {formatCurrency(capacity.dailyDiscretionary, currency)}/day
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
              Across remaining {capacity.remainingDays} days
            </div>
          </div>

          <div style={{ padding: 14, background: 'var(--bg-surface)', border: '1px solid var(--border-color)', borderRadius: 10 }}>
            <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Total Commitments</div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: 'var(--danger)', marginTop: 4 }}>
              {formatCurrency(capacity.essentialBudgetTotal + capacity.unpaidSeparateBills, currency)}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
              Rent, utilities, groceries, EMIs
            </div>
          </div>

          <div style={{ padding: 14, background: 'var(--bg-surface)', border: '1px solid var(--border-color)', borderRadius: 10 }}>
            <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Monthly Savings Target</div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#8b5cf6', marginTop: 4 }}>
              {formatCurrency(capacity.plannedSavings, currency)}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
              Target wealth building
            </div>
          </div>
        </div>
      </div>

      {/* Category Budgets Table */}
      <div className="card">
        <div className="card-header">
          <div>
            <h3 className="card-title">
              <Layers size={18} style={{ color: 'var(--primary)' }} /> Category Spending Limits
            </h3>
            <div className="card-subtitle">Set monthly spending limits per category to prevent lifestyle inflation</div>
          </div>
        </div>

        <div className="table-responsive">
          <table className="table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Type</th>
                <th>Monthly Limit</th>
                <th>Actual Spent</th>
                <th>Remaining</th>
                <th style={{ width: '25%' }}>Budget Progress</th>
                <th style={{ textAlign: 'center' }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {categoryRows.map((cb) => {
                const barClass = cb.percentUsed > 100 ? 'danger' : cb.percentUsed >= 85 ? 'warning' : 'primary';
                return (
                  <tr key={cb.id}>
                    <td>
                      <strong style={{ color: 'var(--text-primary)' }}>{cb.name}</strong>
                    </td>
                    <td>
                      {cb.is_essential ? (
                        <span className="badge" style={{ background: 'var(--danger-light)', color: 'var(--danger)' }}>
                          Essential Living
                        </span>
                      ) : (
                        <span className="badge badge-neutral">Discretionary</span>
                      )}
                    </td>
                    <td style={{ fontWeight: 600 }}>
                      {cb.budgetAmount > 0 ? (
                        formatCurrency(cb.budgetAmount, currency)
                      ) : (
                        <span style={{ color: 'var(--text-muted)' }}>Not set</span>
                      )}
                    </td>
                    <td
                      style={{
                        fontWeight: 700,
                        color: cb.spentAmount > cb.budgetAmount && cb.budgetAmount > 0 ? 'var(--danger)' : 'var(--text-primary)'
                      }}
                    >
                      {formatCurrency(cb.spentAmount, currency)}
                    </td>
                    <td>
                      {cb.budgetAmount > 0 ? (
                        cb.remaining < 0 ? (
                          <span style={{ color: 'var(--danger)', fontWeight: 700 }}>
                            -{formatCurrency(Math.abs(cb.remaining), currency)} Over
                          </span>
                        ) : (
                          <span style={{ color: 'var(--success)', fontWeight: 600 }}>
                            {formatCurrency(cb.remaining, currency)}
                          </span>
                        )
                      ) : (
                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                      )}
                    </td>
                    <td>
                      {cb.budgetAmount > 0 ? (
                        <div>
                          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.74rem', color: 'var(--text-muted)', marginBottom: 2 }}>
                            <span>{cb.percentUsed}% used</span>
                            <span>{formatCurrency(cb.spentAmount, currency)} / {formatCurrency(cb.budgetAmount, currency)}</span>
                          </div>
                          <div className="progress-container">
                            <div className={`progress-bar ${barClass}`} style={{ width: `${Math.min(100, cb.percentUsed)}%` }} />
                          </div>
                        </div>
                      ) : (
                        <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>No limit set</span>
                      )}
                    </td>
                    <td style={{ textAlign: 'center' }}>
                      <button
                        className="btn btn-outline btn-sm"
                        onClick={() =>
                          setEditingBudget({
                            categoryId: cb.id,
                            categoryName: cb.name,
                            amount: cb.budgetAmount > 0 ? String(cb.budgetAmount) : ''
                          })
                        }
                      >
                        <Edit size={13} /> Set Limit
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Set Category Budget Modal */}
      {editingBudget && (
        <div className="modal-backdrop" onClick={() => setEditingBudget(null)}>
          <div className="modal-dialog" style={{ maxWidth: 440 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3 className="modal-title">Set Category Budget</h3>
              <button className="modal-close" onClick={() => setEditingBudget(null)}>
                &times;
              </button>
            </div>
            <form onSubmit={handleSaveBudget}>
              <div className="modal-body">
                <div style={{ padding: 12, background: 'var(--bg-card)', borderRadius: 8, border: '1px solid var(--border-color)', marginBottom: 16 }}>
                  <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Category</span>
                  <h4 style={{ color: 'var(--text-primary)', marginTop: 2 }}>{editingBudget.categoryName}</h4>
                </div>

                <div className="form-group">
                  <label className="form-label">Monthly Spending Limit ({currency})</label>
                  <input
                    type="number"
                    step="100"
                    min="0"
                    className="form-control"
                    placeholder="0.00"
                    value={editingBudget.amount}
                    onChange={(e) => setEditingBudget({ ...editingBudget, amount: e.target.value })}
                    required
                    autoFocus
                  />
                  <small className="form-text">Set to 0 to remove the monthly limit for this category.</small>
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline" onClick={() => setEditingBudget(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Save Budget
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
