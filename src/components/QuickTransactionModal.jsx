import React, { useState } from 'react';
import { useApp } from '../context/AppContext';

export default function QuickTransactionModal() {
  const { isQuickTxnOpen, setIsQuickTxnOpen, categories, addTransaction, user } = useApp();

  const [type, setType] = useState('expense');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [paymentMethod, setPaymentMethod] = useState('upi');
  const [notes, setNotes] = useState('');

  if (!isQuickTxnOpen) return null;

  const filteredCategories = categories.filter((c) => c.type === type);
  const effectiveCatId = categoryId || (filteredCategories[0]?.id ? String(filteredCategories[0].id) : '');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!amount || Number(amount) <= 0 || !description) return;

    addTransaction({
      type,
      amount: Number(amount),
      description,
      category_id: Number(effectiveCatId),
      date,
      payment_method: paymentMethod,
      notes: notes.trim() || undefined
    });

    // Reset & Close
    setAmount('');
    setDescription('');
    setNotes('');
    setIsQuickTxnOpen(false);
  };

  return (
    <div className="modal-backdrop" onClick={() => setIsQuickTxnOpen(false)}>
      <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3 className="modal-title">Record Transaction</h3>
          <button className="modal-close" onClick={() => setIsQuickTxnOpen(false)}>
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            {/* Type selector */}
            <div className="form-group">
              <label className="form-label">Transaction Type</label>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <label
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    padding: 10,
                    background: 'var(--bg-card)',
                    border: `1px solid ${type === 'expense' ? 'var(--danger)' : 'var(--border-color)'}`,
                    borderRadius: 'var(--radius-md)',
                    cursor: 'pointer'
                  }}
                >
                  <input
                    type="radio"
                    name="modal_type"
                    checked={type === 'expense'}
                    onChange={() => {
                      setType('expense');
                      const firstExp = categories.find((c) => c.type === 'expense');
                      if (firstExp) setCategoryId(String(firstExp.id));
                    }}
                  />
                  <span style={{ fontWeight: 600, color: 'var(--danger)' }}>Expense</span>
                </label>

                <label
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    padding: 10,
                    background: 'var(--bg-card)',
                    border: `1px solid ${type === 'income' ? 'var(--success)' : 'var(--border-color)'}`,
                    borderRadius: 'var(--radius-md)',
                    cursor: 'pointer'
                  }}
                >
                  <input
                    type="radio"
                    name="modal_type"
                    checked={type === 'income'}
                    onChange={() => {
                      setType('income');
                      const firstInc = categories.find((c) => c.type === 'income');
                      if (firstInc) setCategoryId(String(firstInc.id));
                    }}
                  />
                  <span style={{ fontWeight: 600, color: 'var(--success)' }}>Income</span>
                </label>
              </div>
            </div>

            {/* Amount */}
            <div className="form-group">
              <label className="form-label">Amount ({user?.currency || '₹'})</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                className="form-control"
                placeholder="0.00"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
                autoFocus
              />
            </div>

            {/* Description */}
            <div className="form-group">
              <label className="form-label">Description</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. Nature's Basket Groceries, Client Milestone, Fuel"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                required
              />
            </div>

            {/* Category & Date */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
              <div className="form-group">
                <label className="form-label">Category</label>
                <select
                  className="form-select"
                  value={effectiveCatId}
                  onChange={(e) => setCategoryId(e.target.value)}
                  required
                >
                  {filteredCategories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Date</label>
                <input
                  type="date"
                  className="form-control"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  required
                />
              </div>
            </div>

            {/* Payment Method */}
            <div className="form-group">
              <label className="form-label">Payment Method</label>
              <select
                className="form-select"
                value={paymentMethod}
                onChange={(e) => setPaymentMethod(e.target.value)}
              >
                <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                <option value="bank_transfer">Bank Transfer (NEFT/IMPS)</option>
                <option value="credit_card">Credit Card</option>
                <option value="debit_card">Debit Card</option>
                <option value="cash">Cash</option>
                <option value="cheque">Cheque</option>
                <option value="other">Other</option>
              </select>
            </div>

            {/* Optional Notes */}
            <div className="form-group">
              <label className="form-label">Notes (Optional)</label>
              <textarea
                className="form-control"
                rows="2"
                placeholder="Additional details or reference note"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-outline" onClick={() => setIsQuickTxnOpen(false)}>
              Cancel
            </button>
            <button type="submit" className="btn btn-primary">
              Save Transaction
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
