import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { evaluatePurchaseAffordability, getMonthlyCapacityPlan } from '../services/budgetEngine';
import { getAccountBalance, formatCurrency } from '../services/calculations';
import { HelpCircle, Calculator, CheckCircle2, AlertTriangle, AlertOctagon } from 'lucide-react';

export default function AffordabilityView() {
  const {
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
    <div style={{ maxWidth: 860, margin: '0 auto' }}>
      {/* 3 Metric Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 24 }}>
        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Bank Balance</div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: 'var(--text-primary)', marginTop: 4 }}>
            {formatCurrency(accountBalance, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>Current liquid funds</div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Unpaid Bills Reserved</div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: 'var(--warning)', marginTop: 4 }}>
            {formatCurrency(upcomingBillsTotal, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>Protected for upcoming bills</div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Remaining Flexible Budget</div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: '#38bdf8', marginTop: 4 }}>
            {formatCurrency(capacity.remainingFlexible, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>Safe discretionary limit</div>
        </div>
      </div>

      {/* Main Evaluator Card */}
      <div className="card" style={{ marginBottom: 32 }}>
        <div className="card-header">
          <div>
            <h3 className="card-title">
              <HelpCircle size={20} style={{ color: 'var(--primary)' }} />
              "Can I Afford This?" Purchase Calculator
            </h3>
            <div className="card-subtitle">
              Test whether a planned expense will harm upcoming bills or derail your savings goal
            </div>
          </div>
        </div>

        <form onSubmit={handleEvaluate}>
          <div className="form-group">
            <label className="form-label">What purchase are you considering?</label>
            <input
              type="text"
              className="form-control"
              placeholder="e.g. Sony WH-1000XM5 Headphones, Ergonomic Desk Chair, Weekend Getaway"
              value={purchaseName}
              onChange={(e) => setPurchaseName(e.target.value)}
              required
              autoFocus
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
            <div className="form-group">
              <label className="form-label">Estimated Price ({currency})</label>
              <input
                type="number"
                step="0.01"
                min="1"
                className="form-control"
                placeholder="12999.00"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Purchase Classification</label>
              <select
                className="form-select"
                value={isEssential}
                onChange={(e) => setIsEssential(e.target.value)}
              >
                <option value="0">Discretionary / Lifestyle (Optional)</option>
                <option value="1">Essential / Urgent (Work equipment, health, repair)</option>
              </select>
            </div>
          </div>

          <button type="submit" className="btn btn-primary btn-lg" style={{ width: '100%', marginTop: 8 }}>
            <Calculator size={18} /> Run Affordability Evaluation
          </button>
        </form>

        {/* Results Presentation */}
        {result && (
          <div
            style={{
              marginTop: 24,
              padding: 22,
              borderRadius: 12,
              backgroundColor: 'var(--bg-surface)',
              border: '1px solid var(--border-color)'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
              <span style={{ fontSize: '0.8rem', textTransform: 'uppercase', color: 'var(--text-muted)', fontWeight: 700 }}>
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
                style={{ padding: '6px 14px', fontSize: '0.82rem' }}
              >
                {result.status === 'affordable'
                  ? 'Affordable'
                  : result.status === 'tight'
                  ? 'Budget Tight'
                  : 'Not Recommended'}
              </span>
            </div>

            <h4 style={{ fontSize: '1.05rem', color: 'var(--text-primary)', marginBottom: 10 }}>
              {result.verdict}
            </h4>

            <ul
              style={{
                fontSize: '0.86rem',
                color: 'var(--text-secondary)',
                paddingLeft: 20,
                display: 'flex',
                flexDirection: 'column',
                gap: 8
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
                gap: 16,
                marginTop: 18,
                padding: 16,
                background: 'rgba(0,0,0,0.25)',
                borderRadius: 10
              }}
            >
              <div>
                <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>Daily Discretionary Now</div>
                <div style={{ fontWeight: 700, fontSize: '1.1rem', color: 'var(--text-primary)', marginTop: 2 }}>
                  {formatCurrency(result.impacts.currentDaily, currency)}/day
                </div>
              </div>
              <div>
                <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>Daily Allowance After</div>
                <div
                  style={{
                    fontWeight: 700,
                    fontSize: '1.1rem',
                    color: result.impacts.newDaily > 0 ? 'var(--primary)' : 'var(--danger)',
                    marginTop: 2
                  }}
                >
                  {formatCurrency(result.impacts.newDaily, currency)}/day
                </div>
              </div>
            </div>

            {result.alternatives && result.alternatives.length > 0 && (
              <div style={{ marginTop: 20 }}>
                <h5 style={{ fontSize: '0.92rem', color: 'var(--text-primary)', marginBottom: 10 }}>
                  Practical Financial Alternatives:
                </h5>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  {result.alternatives.map((a, i) => (
                    <div
                      key={i}
                      style={{
                        padding: '12px 14px',
                        background: 'rgba(255,255,255,0.03)',
                        borderRadius: 8,
                        border: '1px solid var(--border-color)'
                      }}
                    >
                      <strong style={{ fontSize: '0.86rem', color: 'var(--primary)' }}>{a.title}</strong>
                      <p style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', margin: '4px 0 0' }}>
                        {a.description}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', marginTop: 16, fontStyle: 'italic' }}>
              {result.disclaimer}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
