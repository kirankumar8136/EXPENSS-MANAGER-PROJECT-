import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { Check, CheckCircle2 } from 'lucide-react';
import { formatCurrency } from '../services/calculations';

export default function PayBillModal() {
  const { payBillData, setPayBillData, recordBillPayment, user } = useApp();

  const [amountPaid, setAmountPaid] = useState('');
  const [paidDate, setPaidDate] = useState(new Date().toISOString().split('T')[0]);
  const [paymentMethod, setPaymentMethod] = useState('upi');
  const [paymentRef, setPaymentRef] = useState('');
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (payBillData) {
      setAmountPaid(String(payBillData.amount || ''));
      setPaidDate(new Date().toISOString().split('T')[0]);
    }
  }, [payBillData]);

  if (!payBillData) return null;

  const currency = user?.currency || '₹';

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!amountPaid || Number(amountPaid) <= 0) return;

    recordBillPayment({
      billId: payBillData.billId,
      amountPaid: Number(amountPaid),
      paidDate,
      paymentMethod,
      paymentRef,
      notes
    });

    setPayBillData(null);
  };

  return (
    <div className="modal-backdrop" onClick={() => setPayBillData(null)}>
      <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3 className="modal-title">Record Bill Payment</h3>
          <button className="modal-close" onClick={() => setPayBillData(null)}>
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            {/* Bill Summary Banner */}
            <div
              style={{
                padding: 14,
                background: 'var(--bg-card)',
                borderRadius: 8,
                border: '1px solid var(--border-color)',
                marginBottom: 16
              }}
            >
              <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                Bill Name
              </div>
              <div style={{ fontWeight: 700, fontSize: '1.05rem', color: 'var(--text-primary)' }}>
                {payBillData.name}
              </div>
              <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: 4 }}>
                Current Due Date: <span style={{ color: 'var(--warning)', fontWeight: 600 }}>{payBillData.dueDate}</span>
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Actual Amount Paid ({currency})</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                className="form-control"
                value={amountPaid}
                onChange={(e) => setAmountPaid(e.target.value)}
                required
                autoFocus
              />
              <small className="form-text">
                Adjust this if your actual billed charge differs from the expected amount.
              </small>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
              <div className="form-group">
                <label className="form-label">Payment Date</label>
                <input
                  type="date"
                  className="form-control"
                  value={paidDate}
                  onChange={(e) => setPaidDate(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Payment Method</label>
                <select
                  className="form-select"
                  value={paymentMethod}
                  onChange={(e) => setPaymentMethod(e.target.value)}
                >
                  <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                  <option value="bank_transfer">Net Banking / Transfer</option>
                  <option value="credit_card">Credit Card</option>
                  <option value="debit_card">Debit Card</option>
                  <option value="cash">Cash</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Payment Reference / UTR (Optional)</label>
              <input
                type="text"
                className="form-control"
                placeholder="e.g. Bank UTR / UPI Ref ID"
                value={paymentRef}
                onChange={(e) => setPaymentRef(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label">Notes (Optional)</label>
              <input
                type="text"
                className="form-control"
                placeholder="Notes for this payment occurrence"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-outline" onClick={() => setPayBillData(null)}>
              Cancel
            </button>
            <button type="submit" className="btn btn-success">
              <Check size={16} />
              Confirm Payment & Link Expense
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
