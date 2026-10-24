import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import { getDueStatus } from '../services/recurrence';
import {
  CalendarClock,
  Plus,
  Calendar,
  AlertOctagon,
  Shield,
  History,
  Trash2
} from 'lucide-react';

export default function BillsView() {
  const {
    recurringBills,
    billPayments,
    categories,
    user,
    addBill,
    deleteBill,
    setPayBillData,
    setActiveView
  } = useApp();

  const [isAddBillOpen, setIsAddBillOpen] = useState(false);
  const [newBill, setNewBill] = useState({
    name: '',
    category_id: '',
    amount: '',
    frequency: 'monthly',
    next_due_date: new Date().toISOString().split('T')[0],
    notes: ''
  });

  const currency = user?.currency || '₹';
  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];
  const endOfMonthStr = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];

  // Group bills
  const overdueBills = [];
  const thisMonthBills = [];
  const upcomingYearlyBills = [];

  let overdueTotal = 0;
  let dueThisMonthTotal = 0;
  let monthlyRunRate = 0;
  let yearlyRunRate = 0;

  for (const b of recurringBills) {
    if (b.status !== 'active') continue;
    const amt = Number(b.amount || 0);

    // Compute run-rate
    const monthlyEquiv = {
      weekly: amt * 4.333,
      monthly: amt,
      bi_monthly: amt / 2,
      quarterly: amt / 3,
      half_yearly: amt / 6,
      yearly: amt / 12
    }[b.frequency] || amt;

    monthlyRunRate += monthlyEquiv;
    yearlyRunRate += monthlyEquiv * 12;

    if (b.frequency === 'yearly' && b.next_due_date > endOfMonthStr) {
      upcomingYearlyBills.push(b);
      continue;
    }

    if (b.next_due_date < todayStr) {
      overdueBills.push(b);
      overdueTotal += amt;
    } else if (b.next_due_date <= endOfMonthStr) {
      thisMonthBills.push(b);
      dueThisMonthTotal += amt;
    } else {
      thisMonthBills.push(b);
    }
  }

  const handleAddBillSubmit = (e) => {
    e.preventDefault();
    if (!newBill.name || !newBill.amount || Number(newBill.amount) <= 0) return;

    const firstCat = categories.find((c) => c.type === 'expense');
    const catId = newBill.category_id ? Number(newBill.category_id) : (firstCat?.id || 1);
    const dueDay = new Date(newBill.next_due_date).getDate();

    addBill({
      name: newBill.name,
      category_id: catId,
      amount: Number(newBill.amount),
      frequency: newBill.frequency,
      due_day: dueDay,
      next_due_date: newBill.next_due_date,
      notes: newBill.notes
    });

    setIsAddBillOpen(false);
    setNewBill({
      name: '',
      category_id: '',
      amount: '',
      frequency: 'monthly',
      next_due_date: new Date().toISOString().split('T')[0],
      notes: ''
    });
  };

  return (
    <div>
      {/* 4 Totals Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 24 }}>
        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: `1px solid ${overdueTotal > 0 ? 'rgba(239, 68, 68, 0.4)' : 'var(--border-color)'}` }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--danger)', textTransform: 'uppercase', fontWeight: 700 }}>
            Overdue Bills
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: 'var(--danger)', marginTop: 4 }}>
            {formatCurrency(overdueTotal, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
            Requires immediate payment
          </div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--warning)', textTransform: 'uppercase', fontWeight: 700 }}>
            Due This Month
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: 'var(--warning)', marginTop: 4 }}>
            {formatCurrency(dueThisMonthTotal, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
            Unpaid scheduled commitments
          </div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--primary)', textTransform: 'uppercase', fontWeight: 700 }}>
            Monthly Run-Rate
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: 'var(--primary)', marginTop: 4 }}>
            {formatCurrency(monthlyRunRate, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
            Normalized monthly bill cost
          </div>
        </div>

        <div style={{ padding: 16, background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: '#8b5cf6', textTransform: 'uppercase', fontWeight: 700 }}>
            Annual Commitments
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 700, color: '#8b5cf6', marginTop: 4 }}>
            {formatCurrency(yearlyRunRate, currency)}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4 }}>
            Yearly & subscription total
          </div>
        </div>
      </div>

      {/* Header Controls */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20 }}>
        <div>
          <button className="btn btn-outline btn-sm" onClick={() => setActiveView('calendar')}>
            <Calendar size={14} /> View Monthly Calendar
          </button>
        </div>
        <div>
          <button className="btn btn-primary" onClick={() => setIsAddBillOpen(true)}>
            <Plus size={15} /> Add Recurring Bill
          </button>
        </div>
      </div>

      {/* Overdue Bills Section */}
      {overdueBills.length > 0 && (
        <div className="card" style={{ marginBottom: 24, borderColor: 'rgba(239, 68, 68, 0.4)', background: 'rgba(239, 68, 68, 0.03)' }}>
          <div className="card-header">
            <h3 className="card-title" style={{ color: 'var(--danger)' }}>
              <AlertOctagon size={18} /> Overdue Bills ({overdueBills.length})
            </h3>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {overdueBills.map((b) => {
              const status = getDueStatus(b.next_due_date);
              return (
                <div key={b.id} className="bill-widget-item" style={{ borderLeft: '4px solid var(--danger)' }}>
                  <div className="bill-widget-left">
                    <div>
                      <div className="bill-widget-name">{b.name}</div>
                      <div className="bill-widget-due status-danger">{status.text} (Was due {b.next_due_date})</div>
                    </div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <span className="badge badge-neutral">{b.frequency.toUpperCase()}</span>
                    <div style={{ fontSize: '1.1rem', fontWeight: 700, color: 'var(--danger)' }}>
                      {formatCurrency(b.amount, currency)}
                    </div>
                    <button
                      className="btn btn-danger btn-sm"
                      onClick={() =>
                        setPayBillData({
                          billId: b.id,
                          name: b.name,
                          amount: b.amount,
                          dueDate: b.next_due_date
                        })
                      }
                    >
                      Record Payment
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Due This Month Section */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div className="card-header">
          <h3 className="card-title">
            <CalendarClock size={18} style={{ color: 'var(--warning)' }} /> Active Scheduled Bills ({thisMonthBills.length})
          </h3>
        </div>

        {thisMonthBills.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-title">No Pending Bills Scheduled</div>
            <div className="empty-state-text">You have no pending bills scheduled for this month.</div>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {thisMonthBills.map((b) => {
              const status = getDueStatus(b.next_due_date);
              const cat = categories.find((c) => c.id === b.category_id);
              return (
                <div key={b.id} className="bill-widget-item">
                  <div className="bill-widget-left">
                    <div>
                      <div className="bill-widget-name">{b.name}</div>
                      <div className="bill-widget-due" style={{ color: 'var(--text-muted)' }}>
                        <span style={{ color: status.isDueToday ? 'var(--danger)' : 'var(--warning)', fontWeight: 600 }}>
                          {status.text}
                        </span>
                        {' '}• Category: {cat?.name || 'General'}
                      </div>
                    </div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <span className="badge badge-neutral">{b.frequency.toUpperCase()}</span>
                    <div style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--text-primary)' }}>
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
                      Record Payment
                    </button>
                    <button
                      className="btn btn-outline btn-sm"
                      style={{ padding: '6px', color: 'var(--text-muted)' }}
                      title="Delete Bill"
                      onClick={() => {
                        if (window.confirm(`Delete recurring bill "${b.name}"?`)) {
                          deleteBill(b.id);
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
      </div>

      {/* Upcoming Yearly Bills Section */}
      {upcomingYearlyBills.length > 0 && (
        <div className="card" style={{ marginBottom: 24 }}>
          <div className="card-header">
            <h3 className="card-title">
              <Shield size={18} style={{ color: '#06b6d4' }} /> Upcoming Annual Commitments ({upcomingYearlyBills.length})
            </h3>
            <div className="card-subtitle">Yearly bills scheduled later in the year</div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {upcomingYearlyBills.map((b) => (
              <div key={b.id} className="bill-widget-item">
                <div className="bill-widget-left">
                  <div>
                    <div className="bill-widget-name">{b.name}</div>
                    <div className="bill-widget-due status-neutral">Due on {b.next_due_date}</div>
                  </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                  <span className="badge badge-info">ANNUAL</span>
                  <div style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                    {formatCurrency(b.amount, currency)}
                  </div>
                  <button
                    className="btn btn-outline btn-sm"
                    onClick={() =>
                      setPayBillData({
                        billId: b.id,
                        name: b.name,
                        amount: b.amount,
                        dueDate: b.next_due_date
                      })
                    }
                  >
                    Pay Early
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Payment History Audit Trail */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">
            <History size={18} style={{ color: 'var(--primary)' }} /> Bill Payment History
          </h3>
          <div className="card-subtitle">Atomic payment logs synced with expense ledger</div>
        </div>

        {billPayments.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-title">No Payments Recorded Yet</div>
            <div className="empty-state-text">When you record payments for your bills, the audit trail appears here.</div>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Bill Name</th>
                  <th>Date Paid</th>
                  <th>Method</th>
                  <th>Reference / Notes</th>
                  <th style={{ textAlign: 'right' }}>Amount Paid</th>
                </tr>
              </thead>
              <tbody>
                {billPayments.map((p) => (
                  <tr key={p.id}>
                    <td><strong style={{ color: 'var(--text-primary)' }}>{p.bill_name}</strong></td>
                    <td style={{ color: 'var(--success)', fontWeight: 600 }}>{p.paid_date}</td>
                    <td>{p.payment_method?.toUpperCase()}</td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>{p.payment_reference || p.notes || '—'}</td>
                    <td style={{ textAlign: 'right', fontWeight: 700, color: 'var(--text-primary)' }}>
                      {formatCurrency(p.amount_paid, currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add Recurring Bill Modal */}
      {isAddBillOpen && (
        <div className="modal-backdrop" onClick={() => setIsAddBillOpen(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3 className="modal-title">Add Recurring Bill</h3>
              <button className="modal-close" onClick={() => setIsAddBillOpen(false)}>&times;</button>
            </div>
            <form onSubmit={handleAddBillSubmit}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">Bill / Obligation Name</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. Electricity Bill, Netflix Premium, Rent"
                    value={newBill.name}
                    onChange={(e) => setNewBill({ ...newBill, name: e.target.value })}
                    required
                    autoFocus
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                  <div className="form-group">
                    <label className="form-label">Category</label>
                    <select
                      className="form-select"
                      value={newBill.category_id}
                      onChange={(e) => setNewBill({ ...newBill, category_id: e.target.value })}
                    >
                      {categories.filter((c) => c.type === 'expense').map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Expected Amount ({currency})</label>
                    <input
                      type="number"
                      step="0.01"
                      min="1"
                      className="form-control"
                      placeholder="0.00"
                      value={newBill.amount}
                      onChange={(e) => setNewBill({ ...newBill, amount: e.target.value })}
                      required
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                  <div className="form-group">
                    <label className="form-label">Frequency</label>
                    <select
                      className="form-select"
                      value={newBill.frequency}
                      onChange={(e) => setNewBill({ ...newBill, frequency: e.target.value })}
                    >
                      <option value="monthly">Monthly</option>
                      <option value="weekly">Weekly</option>
                      <option value="bi_monthly">Every 2 Months</option>
                      <option value="quarterly">Quarterly</option>
                      <option value="half_yearly">Half-Yearly</option>
                      <option value="yearly">Yearly (Annual)</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Next Due Date</label>
                    <input
                      type="date"
                      className="form-control"
                      value={newBill.next_due_date}
                      onChange={(e) => setNewBill({ ...newBill, next_due_date: e.target.value })}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Notes (Optional)</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Account number or reminder note"
                    value={newBill.notes}
                    onChange={(e) => setNewBill({ ...newBill, notes: e.target.value })}
                  />
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline" onClick={() => setIsAddBillOpen(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Save Recurring Bill
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
