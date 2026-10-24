import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { evaluatePurchaseAffordability, getMonthlyCapacityPlan } from '../services/budgetEngine';
import { getAccountBalance, formatCurrency } from '../services/calculations';
import { HelpCircle, Calculator, CheckCircle2, AlertTriangle, AlertOctagon } from 'lucide-react';

export default function AffordabilityModal() {
  const {
    isAffordModalOpen,
    setIsAffordModalOpen,
    transactions,
    settings,
    openingBalance,
    recurringBills,
    budgets,
    categories,
    user
  } = useApp();

  const [purchaseName, setPurchaseName] = useState('');
  const [price, setPrice] = useState('');
  const [isEssential, setIsEssential] = useState('0');
  const [result, setResult] = useState(null);

  if (!isAffordModalOpen) return null;

  const currency = user?.currency || '₹';

  // Compute live current month parameters
  const now = new Date();
  const curYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const monthStart = `${curYm}-01`;
  const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

  const monthTxns = transactions.filter((t) => t.date >= monthStart && t.date <= monthEnd);
  const accountBalance = getAccountBalance(openingBalance, transactions);

  const capacity = getMonthlyCapacityPlan({
    monthlyIncome: settings.monthly_income,
    transactionsThisMonth: monthTxns,
    recurringBills,
    categoryBudgets: budgets,
    categories,
    monthlySavingsTarget: settings.monthly_savings_target,
    savingsStyle: settings.savings_style
  });

  // Calculate upcoming unpaid bills total
  const todayStr = now.toISOString().split('T')[0];
  const upcomingBillsTotal = recurringBills
    .filter((b) => b.status === 'active' && b.next_due_date >= todayStr && b.next_due_date <= monthEnd)
    .reduce((sum, b) => sum + Number(b.amount || 0), 0);

  const handleEvaluate = (e) => {
    e.preventDefault();
    if (!price || Number(price) <= 0 || !purchaseName) return;

    const evaluation = evaluatePurchaseAffordability({
      purchaseName,
      price: Number(price),
      isEssential: isEssential === '1',
      accountBalance,
      upcomingBillsTotal,
      remainingFlexible: capacity.remainingFlexible,
      plannedSavingsTarget: capacity.plannedSavings,
      dailyDiscretionary: capacity.dailyDiscretionary,
      remainingDays: capacity.remainingDays,
      currency
    });

    setResult(evaluation);
  };

  return (
    <div className="modal-backdrop" onClick={() => setIsAffordModalOpen(false)}>
      <div className="modal-dialog" style={{ maxWidth: 580 }} onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3 className="modal-title">
            <HelpCircle size={18} style={{ color: 'var(--primary)' }} />
            Can I Afford This?
          </h3>
          <button className="modal-close" onClick={() => setIsAffordModalOpen(false)}>
            &times;
          </button>
        </div>

        <div className="modal-body">
          <p style={{ fontSize: '0.84rem', color: 'var(--text-secondary)', marginBottom: 16 }}>
            Enter a planned purchase. The engine tests your liquid bank balance, upcoming unpaid bills,
            and remaining monthly flexible budget.
          </p>

          <form onSubmit={handleEvaluate}>
            <div className="form-group">
              <label className="form-label">Purchase Item / Purpose</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. Sony WH-1000XM5 Headphones, Weekend Trip"
                value={purchaseName}
                onChange={(e) => setPurchaseName(e.target.value)}
                required
                autoFocus
              />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
              <div className="form-group">
                <label className="form-label">Price ({currency})</label>
                <input
                  type="number"
                  step="0.01"
                  min="1"
                  className="form-control"
                  placeholder="12500.00"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Classification</label>
                <select
                  className="form-select"
                  value={isEssential}
                  onChange={(e) => setIsEssential(e.target.value)}
                >
                  <option value="0">Discretionary / Lifestyle</option>
                  <option value="1">Essential / Urgent</option>
                </select>
              </div>
            </div>

            <button type="submit" className="btn btn-primary" style={{ width: '100%', marginTop: 4 }}>
              <Calculator size={16} />
              Evaluate Affordability
            </button>
          </form>

          {/* Results Box */}
          {result && (
            <div
              style={{
                marginTop: 20,
                padding: 18,
                borderRadius: 12,
                backgroundColor: 'var(--bg-surface)',
                border: '1px solid var(--border-color)'
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
                <span style={{ fontSize: '0.78rem', textTransform: 'uppercase', color: 'var(--text-muted)', fontWeight: 700 }}>
                  Evaluation Verdict
                </span>
                <span
                  className={`badge ${
                    result.status === 'affordable'
                      ? 'badge-success'
                      : result.status === 'tight'
                      ? 'badge-warning'
                      : 'badge-danger'
                  }`}
                >
                  {result.status === 'affordable'
                    ? 'Affordable'
                    : result.status === 'tight'
                    ? 'Budget Tight'
                    : 'Not Recommended'}
                </span>
              </div>

              <h4 style={{ fontSize: '0.98rem', color: 'var(--text-primary)', marginBottom: 8 }}>
                {result.verdict}
              </h4>

              <ul
                style={{
                  fontSize: '0.82rem',
                  color: 'var(--text-secondary)',
                  paddingLeft: 20,
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 6
                }}
              >
                {result.reasons.map((r, idx) => (
                  <li key={idx}>{r}</li>
                ))}
              </ul>

              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: '1fr 1fr',
                  gap: 12,
                  marginTop: 14,
                  padding: 12,
                  background: 'rgba(0,0,0,0.2)',
                  borderRadius: 8
                }}
              >
                <div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Daily Discretionary Now</div>
                  <div style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                    {formatCurrency(result.impacts.currentDaily, currency)}/day
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Daily Allowance After</div>
                  <div
                    style={{
                      fontWeight: 700,
                      color: result.impacts.newDaily > 0 ? 'var(--primary)' : 'var(--danger)'
                    }}
                  >
                    {formatCurrency(result.impacts.newDaily, currency)}/day
                  </div>
                </div>
              </div>

              {result.alternatives && result.alternatives.length > 0 && (
                <div style={{ marginTop: 16 }}>
                  <h5 style={{ fontSize: '0.86rem', color: 'var(--text-primary)', marginBottom: 8 }}>
                    Smart Alternatives:
                  </h5>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {result.alternatives.map((a, i) => (
                      <div
                        key={i}
                        style={{
                          padding: '10px 12px',
                          background: 'rgba(255,255,255,0.03)',
                          borderRadius: 8,
                          border: '1px solid var(--border-color)'
                        }}
                      >
                        <strong style={{ fontSize: '0.82rem', color: 'var(--primary)' }}>{a.title}</strong>
                        <p style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', margin: '2px 0 0' }}>
                          {a.description}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: 12, fontStyle: 'italic' }}>
                {result.disclaimer}
              </div>
            </div>
          )}
        </div>

        <div className="modal-footer">
          <button className="btn btn-outline" onClick={() => setIsAffordModalOpen(false)}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
