import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import { Target, Plus, PlusCircle, Trash2, History } from 'lucide-react';

export default function SavingsGoalsView() {
  const { savingsGoals, user, addSavingsGoal, deleteSavingsGoal, contributeToGoal } = useApp();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [newGoal, setNewGoal] = useState({
    name: '',
    target_amount: '',
    current_amount: '',
    target_date: `${new Date().getFullYear() + 1}-12-31`,
    priority: 'medium',
    category: 'Emergency Fund'
  });

  const [contribData, setContribData] = useState(null); // { goalId, goalName, type, amount, notes }

  const currency = user?.currency || '₹';

  const totalTarget = savingsGoals.reduce((s, g) => s + Number(g.target_amount || 0), 0);
  const totalSaved = savingsGoals.reduce((s, g) => s + Number(g.current_amount || 0), 0);
  const overallPercent = totalTarget > 0 ? Math.round((totalSaved / totalTarget) * 100) : 0;

  // Flatten all contribution history
  const allHistory = [];
  for (const g of savingsGoals) {
    if (g.contributions) {
      for (const c of g.contributions) {
        allHistory.push({
          ...c,
          goalName: g.name
        });
      }
    }
  }
  allHistory.sort((a, b) => b.date.localeCompare(a.date));

  const handleCreateGoalSubmit = (e) => {
    e.preventDefault();
    if (!newGoal.name || !newGoal.target_amount || Number(newGoal.target_amount) <= 0) return;

    addSavingsGoal({
      name: newGoal.name,
      target_amount: Number(newGoal.target_amount),
      current_amount: Number(newGoal.current_amount || 0),
      target_date: newGoal.target_date,
      priority: newGoal.priority,
      category: newGoal.category
    });

    setIsCreateOpen(false);
    setNewGoal({
      name: '',
      target_amount: '',
      current_amount: '',
      target_date: `${new Date().getFullYear() + 1}-12-31`,
      priority: 'medium',
      category: 'Emergency Fund'
    });
  };

  const handleContribSubmit = (e) => {
    e.preventDefault();
    if (!contribData || !contribData.amount || Number(contribData.amount) <= 0) return;

    contributeToGoal(contribData.goalId, {
      type: contribData.type || 'deposit',
      amount: Number(contribData.amount),
      date: new Date().toISOString().split('T')[0],
      notes: contribData.notes
    });

    setContribData(null);
  };

  return (
    <div>
      {/* Overview KPI Banner */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 24 }}>
        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Total Target Wealth</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)', marginTop: 4 }}>
            {formatCurrency(totalTarget, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>Across {savingsGoals.length} goals</div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Accumulated Savings</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: '#8b5cf6', marginTop: 4 }}>
            {formatCurrency(totalSaved, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>{overallPercent}% of total target reached</div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Remaining To Accumulate</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--warning)', marginTop: 4 }}>
            {formatCurrency(Math.max(0, totalTarget - totalSaved), currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>Remaining wealth gap</div>
        </div>
      </div>

      {/* Header Controls */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20 }}>
        <div>
          <h3 style={{ fontSize: '1.15rem', color: 'var(--text-primary)', margin: 0 }}>Active Savings Goals</h3>
        </div>
        <button className="btn btn-primary" onClick={() => setIsCreateOpen(true)}>
          <Plus size={15} /> Create New Goal
        </button>
      </div>

      {/* Goals Cards Grid */}
      {savingsGoals.length === 0 ? (
        <div className="card empty-state" style={{ marginBottom: 24 }}>
          <div className="empty-state-title">No Savings Goals Defined</div>
          <div className="empty-state-text">Setting goals helps you prioritize saving for an emergency fund, travel, or gadget purchases.</div>
          <button className="btn btn-primary btn-sm" onClick={() => setIsCreateOpen(true)}>
            Create Your First Goal
          </button>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 20, marginBottom: 32 }}>
          {savingsGoals.map((g) => {
            const pct = g.target_amount > 0 ? Math.round((g.current_amount / g.target_amount) * 100) : 0;
            const remaining = Math.max(0, g.target_amount - g.current_amount);
            const isComplete = g.current_amount >= g.target_amount;

            // Monthly pace calculation
            const today = new Date();
            const targetDt = new Date(g.target_date);
            const monthsLeft = Math.max(1, (targetDt.getFullYear() - today.getFullYear()) * 12 + (targetDt.getMonth() - today.getMonth()));
            const suggestedMonthly = Math.round(remaining / monthsLeft);

            return (
              <div key={g.id} className="card" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 12 }}>
                    <div>
                      <span className="badge badge-neutral" style={{ marginBottom: 6 }}>
                        {g.category || 'General'}
                      </span>
                      <h4 style={{ fontSize: '1.1rem', color: 'var(--text-primary)', margin: 0 }}>{g.name}</h4>
                    </div>
                    {isComplete ? (
                      <span className="badge badge-success">Completed</span>
                    ) : (
                      <span className="badge badge-warning">{g.priority?.toUpperCase()} Priority</span>
                    )}
                  </div>

                  <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginTop: 16 }}>
                    <span style={{ fontSize: '1.4rem', fontWeight: 800, color: '#8b5cf6' }}>
                      {formatCurrency(g.current_amount, currency)}
                    </span>
                    <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                      of {formatCurrency(g.target_amount, currency)}
                    </span>
                  </div>

                  <div className="progress-container" style={{ height: 10, margin: '10px 0' }}>
                    <div className={`progress-bar ${isComplete ? 'success' : 'primary'}`} style={{ width: `${Math.min(100, pct)}%` }} />
                  </div>

                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', color: 'var(--text-muted)', marginBottom: 16 }}>
                    <span>{pct}% funded</span>
                    <span>{formatCurrency(remaining, currency)} left</span>
                  </div>

                  {!isComplete && (
                    <div style={{ padding: '10px 12px', background: 'rgba(255,255,255,0.03)', borderRadius: 8, border: '1px solid var(--border-color)', marginBottom: 16 }}>
                      <span style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>Suggested Pace: </span>
                      <strong style={{ color: 'var(--primary)', fontSize: '0.82rem' }}>
                        {formatCurrency(suggestedMonthly, currency)}/month
                      </strong>
                      <div style={{ color: 'var(--text-muted)', fontSize: '0.72rem', marginTop: 2 }}>
                        Target Date: {g.target_date} ({monthsLeft} mo left)
                      </div>
                    </div>
                  )}
                </div>

                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingTop: 14, borderTop: '1px solid var(--border-color)' }}>
                  <button
                    className="btn btn-outline btn-sm"
                    onClick={() => setContribData({ goalId: g.id, goalName: g.name, type: 'deposit', amount: '', notes: '' })}
                  >
                    <PlusCircle size={14} /> Deposit / Withdraw
                  </button>
                  <button
                    className="btn btn-outline btn-sm"
                    style={{ color: 'var(--text-muted)' }}
                    title="Delete Goal"
                    onClick={() => {
                      if (window.confirm(`Delete goal "${g.name}"?`)) {
                        deleteSavingsGoal(g.id);
                      }
                    }}
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Contribution Audit Trail */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">
            <History size={18} style={{ color: 'var(--primary)' }} /> Contribution Audit Trail
          </h3>
        </div>

        {allHistory.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-title">No Contributions Logged</div>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Goal Name</th>
                  <th>Type</th>
                  <th>Notes</th>
                  <th style={{ textAlign: 'right' }}>Amount</th>
                </tr>
              </thead>
              <tbody>
                {allHistory.map((h, i) => (
                  <tr key={i}>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.84rem' }}>{h.date}</td>
                    <td><strong style={{ color: 'var(--text-primary)' }}>{h.goalName}</strong></td>
                    <td>
                      <span className={`badge ${h.type === 'deposit' ? 'badge-success' : 'badge-danger'}`}>
                        {h.type.toUpperCase()}
                      </span>
                    </td>
                    <td style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{h.notes || '—'}</td>
                    <td style={{ textAlign: 'right', fontWeight: 700, color: h.type === 'deposit' ? 'var(--success)' : 'var(--danger)' }}>
                      {h.type === 'deposit' ? '+' : '-'}{formatCurrency(h.amount, currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Create Goal Modal */}
      {isCreateOpen && (
        <div className="modal-backdrop" onClick={() => setIsCreateOpen(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3 className="modal-title">Create Savings Goal</h3>
              <button className="modal-close" onClick={() => setIsCreateOpen(false)}>&times;</button>
            </div>
            <form onSubmit={handleCreateGoalSubmit}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">Goal Name</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. MacBook Pro M3, Emergency Fund, Bali Vacation"
                    value={newGoal.name}
                    onChange={(e) => setNewGoal({ ...newGoal, name: e.target.value })}
                    required
                    autoFocus
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                  <div className="form-group">
                    <label className="form-label">Target Amount ({currency})</label>
                    <input
                      type="number"
                      step="0.01"
                      min="100"
                      className="form-control"
                      placeholder="50000.00"
                      value={newGoal.target_amount}
                      onChange={(e) => setNewGoal({ ...newGoal, target_amount: e.target.value })}
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Already Saved</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="form-control"
                      placeholder="0.00"
                      value={newGoal.current_amount}
                      onChange={(e) => setNewGoal({ ...newGoal, current_amount: e.target.value })}
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                  <div className="form-group">
                    <label className="form-label">Target Date</label>
                    <input
                      type="date"
                      className="form-control"
                      value={newGoal.target_date}
                      onChange={(e) => setNewGoal({ ...newGoal, target_date: e.target.value })}
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Priority</label>
                    <select
                      className="form-select"
                      value={newGoal.priority}
                      onChange={(e) => setNewGoal({ ...newGoal, priority: e.target.value })}
                    >
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High (Essential)</option>
                    </select>
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Category</label>
                  <select
                    className="form-select"
                    value={newGoal.category}
                    onChange={(e) => setNewGoal({ ...newGoal, category: e.target.value })}
                  >
                    <option value="Emergency Fund">Emergency Fund</option>
                    <option value="Technology & Gadgets">Technology & Gadgets</option>
                    <option value="Travel & Vacation">Travel & Vacation</option>
                    <option value="Vehicle">Vehicle</option>
                    <option value="Home & Real Estate">Home & Real Estate</option>
                    <option value="Education">Education</option>
                    <option value="General Wealth">General Wealth</option>
                  </select>
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline" onClick={() => setIsCreateOpen(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Create Goal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Deposit / Withdraw Modal */}
      {contribData && (
        <div className="modal-backdrop" onClick={() => setContribData(null)}>
          <div className="modal-dialog" style={{ maxWidth: 440 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3 className="modal-title">Record Contribution</h3>
              <button className="modal-close" onClick={() => setContribData(null)}>&times;</button>
            </div>
            <form onSubmit={handleContribSubmit}>
              <div className="modal-body">
                <div style={{ padding: 12, background: 'var(--bg-card)', borderRadius: 8, border: '1px solid var(--border-color)', marginBottom: 16 }}>
                  <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Goal</span>
                  <h4 style={{ color: 'var(--text-primary)', marginTop: 2 }}>{contribData.goalName}</h4>
                </div>

                <div className="form-group">
                  <label className="form-label">Action</label>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                    <label
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        padding: 10,
                        background: 'var(--bg-surface)',
                        border: `1px solid ${contribData.type === 'deposit' ? 'var(--success)' : 'var(--border-color)'}`,
                        borderRadius: 'var(--radius-md)',
                        cursor: 'pointer'
                      }}
                    >
                      <input
                        type="radio"
                        name="contrib_type"
                        checked={contribData.type === 'deposit'}
                        onChange={() => setContribData({ ...contribData, type: 'deposit' })}
                      />
                      <span style={{ fontWeight: 600, color: 'var(--success)' }}>Deposit</span>
                    </label>

                    <label
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        padding: 10,
                        background: 'var(--bg-surface)',
                        border: `1px solid ${contribData.type === 'withdrawal' ? 'var(--danger)' : 'var(--border-color)'}`,
                        borderRadius: 'var(--radius-md)',
                        cursor: 'pointer'
                      }}
                    >
                      <input
                        type="radio"
                        name="contrib_type"
                        checked={contribData.type === 'withdrawal'}
                        onChange={() => setContribData({ ...contribData, type: 'withdrawal' })}
                      />
                      <span style={{ fontWeight: 600, color: 'var(--danger)' }}>Withdrawal</span>
                    </label>
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Amount ({currency})</label>
                  <input
                    type="number"
                    step="0.01"
                    min="1"
                    className="form-control"
                    placeholder="0.00"
                    value={contribData.amount}
                    onChange={(e) => setContribData({ ...contribData, amount: e.target.value })}
                    required
                    autoFocus
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Note (Optional)</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. Monthly salary savings contribution"
                    value={contribData.notes}
                    onChange={(e) => setContribData({ ...contribData, notes: e.target.value })}
                  />
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline" onClick={() => setContribData(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Save Entry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
