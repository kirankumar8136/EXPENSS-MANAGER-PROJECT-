import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import {
  Search,
  Filter,
  Download,
  Plus,
  Edit2,
  Trash2,
  ArrowUpDown
} from 'lucide-react';

export default function TransactionsView() {
  const { transactions, categories, user, editTransaction, deleteTransaction, setIsQuickTxnOpen } = useApp();
  const currency = user?.currency || '₹';

  // Filters
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [methodFilter, setMethodFilter] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [sortBy, setSortBy] = useState('date'); // 'date', 'amount'
  const [sortOrder, setSortOrder] = useState('desc'); // 'asc', 'desc'
  const [page, setPage] = useState(1);
  const perPage = 10;

  // Editing state
  const [editingTxn, setEditingTxn] = useState(null);

  // Filter logic
  let filtered = transactions.filter((t) => {
    if (typeFilter && t.type !== typeFilter) return false;
    if (categoryFilter && String(t.category_id) !== String(categoryFilter)) return false;
    if (methodFilter && t.payment_method !== methodFilter) return false;
    if (startDate && t.date < startDate) return false;
    if (endDate && t.date > endDate) return false;
    if (search) {
      const q = search.toLowerCase();
      const matchDesc = t.description?.toLowerCase().includes(q);
      const matchNotes = t.notes?.toLowerCase().includes(q);
      if (!matchDesc && !matchNotes) return false;
    }
    return true;
  });

  // Sort logic
  filtered.sort((a, b) => {
    if (sortBy === 'amount') {
      return sortOrder === 'asc' ? a.amount - b.amount : b.amount - a.amount;
    }
    // date sort
    return sortOrder === 'asc' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date);
  });

  const totalFilteredIncome = filtered
    .filter((t) => t.type === 'income')
    .reduce((s, t) => s + Number(t.amount || 0), 0);
  const totalFilteredExpense = filtered
    .filter((t) => t.type === 'expense')
    .reduce((s, t) => s + Number(t.amount || 0), 0);

  // Pagination
  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
  const paginated = filtered.slice((page - 1) * perPage, page * perPage);

  // CSV Export
  const handleExportCSV = () => {
    const headers = ['ID', 'Date', 'Type', 'Description', 'Amount', 'Category', 'Payment Method', 'Notes'];
    const rows = filtered.map((t) => {
      const cat = categories.find((c) => c.id === t.category_id);
      return [
        t.id,
        t.date,
        t.type.toUpperCase(),
        `"${(t.description || '').replace(/"/g, '""')}"`,
        t.amount,
        `"${cat?.name || ''}"`,
        t.payment_method || '',
        `"${(t.notes || '').replace(/"/g, '""')}"`
      ];
    });

    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `smartspend_transactions_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleSaveEdit = (e) => {
    e.preventDefault();
    if (!editingTxn) return;

    editTransaction(editingTxn.id, {
      description: editingTxn.description,
      amount: Number(editingTxn.amount),
      category_id: Number(editingTxn.category_id),
      date: editingTxn.date,
      type: editingTxn.type,
      payment_method: editingTxn.payment_method,
      notes: editingTxn.notes
    });

    setEditingTxn(null);
  };

  return (
    <div>
      {/* Filter Card */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr', gap: 12, marginBottom: 14 }}>
          <div>
            <input
              type="text"
              className="form-control"
              placeholder="Search description or notes..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </div>

          <div>
            <select
              className="form-select"
              value={typeFilter}
              onChange={(e) => {
                setTypeFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Types (Income & Expense)</option>
              <option value="expense">Expenses Only</option>
              <option value="income">Income Only</option>
            </select>
          </div>

          <div>
            <select
              className="form-select"
              value={categoryFilter}
              onChange={(e) => {
                setCategoryFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Categories</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  [{c.type === 'income' ? 'Income' : 'Expense'}] {c.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <select
              className="form-select"
              value={methodFilter}
              onChange={(e) => {
                setMethodFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Payment Modes</option>
              <option value="upi">UPI</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="credit_card">Credit Card</option>
              <option value="debit_card">Debit Card</option>
              <option value="cash">Cash</option>
            </select>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>From:</span>
              <input
                type="date"
                className="form-control"
                style={{ width: 'auto', padding: '6px 10px' }}
                value={startDate}
                onChange={(e) => {
                  setStartDate(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>To:</span>
              <input
                type="date"
                className="form-control"
                style={{ width: 'auto', padding: '6px 10px' }}
                value={endDate}
                onChange={(e) => {
                  setEndDate(e.target.value);
                  setPage(1);
                }}
              />
            </div>

            {(search || typeFilter || categoryFilter || methodFilter || startDate || endDate) && (
              <button
                className="btn btn-outline btn-sm"
                onClick={() => {
                  setSearch('');
                  setTypeFilter('');
                  setCategoryFilter('');
                  setMethodFilter('');
                  setStartDate('');
                  setEndDate('');
                  setPage(1);
                }}
              >
                Reset
              </button>
            )}
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <button className="btn btn-outline btn-sm" onClick={handleExportCSV}>
              <Download size={14} /> Export CSV
            </button>
            <button className="btn btn-success btn-sm" onClick={() => setIsQuickTxnOpen(true)}>
              <Plus size={14} /> Add Transaction
            </button>
          </div>
        </div>
      </div>

      {/* Summary Totals Bar */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 24 }}>
        <div style={{ padding: '14px 18px', background: 'var(--bg-card)', borderRadius: 10, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Filtered Income</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--success)', marginTop: 2 }}>
            {formatCurrency(totalFilteredIncome, currency)}
          </div>
        </div>
        <div style={{ padding: '14px 18px', background: 'var(--bg-card)', borderRadius: 10, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Filtered Expense</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--danger)', marginTop: 2 }}>
            {formatCurrency(totalFilteredExpense, currency)}
          </div>
        </div>
        <div style={{ padding: '14px 18px', background: 'var(--bg-card)', borderRadius: 10, border: '1px solid var(--border-color)' }}>
          <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Net Cash Flow</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 700, color: totalFilteredIncome >= totalFilteredExpense ? 'var(--text-primary)' : 'var(--danger)', marginTop: 2 }}>
            {formatCurrency(totalFilteredIncome - totalFilteredExpense, currency)}
          </div>
        </div>
      </div>

      {/* Ledger Table */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">
            Ledger Records ({filtered.length} matching)
          </h3>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <button
              className="btn btn-outline btn-sm"
              onClick={() => {
                setSortBy('date');
                setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
              }}
            >
              <ArrowUpDown size={13} /> Date ({sortOrder.toUpperCase()})
            </button>
            <button
              className="btn btn-outline btn-sm"
              onClick={() => {
                setSortBy('amount');
                setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
              }}
            >
              <ArrowUpDown size={13} /> Amount ({sortOrder.toUpperCase()})
            </button>
          </div>
        </div>

        {paginated.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-title">No Transactions Match Filter</div>
            <div className="empty-state-text">Try broadening your search or resetting filters.</div>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Description</th>
                  <th>Category</th>
                  <th>Method</th>
                  <th style={{ textAlign: 'right' }}>Amount</th>
                  <th style={{ textAlign: 'center' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {paginated.map((txn) => {
                  const cat = categories.find((c) => c.id === txn.category_id);
                  return (
                    <tr key={txn.id}>
                      <td style={{ whiteSpace: 'nowrap', color: 'var(--text-muted)', fontSize: '0.84rem' }}>
                        {txn.date}
                      </td>
                      <td>
                        <strong style={{ color: 'var(--text-primary)' }}>{txn.description}</strong>
                        {txn.notes && (
                          <div style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>{txn.notes}</div>
                        )}
                      </td>
                      <td>
                        <span
                          className="badge"
                          style={{
                            background: 'rgba(255,255,255,0.06)',
                            color: cat?.color || 'var(--text-secondary)'
                          }}
                        >
                          {cat?.name || 'Category'}
                        </span>
                      </td>
                      <td style={{ color: 'var(--text-secondary)', fontSize: '0.82rem' }}>
                        {txn.payment_method?.toUpperCase()}
                      </td>
                      <td
                        style={{
                          textAlign: 'right',
                          fontWeight: 700,
                          fontSize: '0.95rem',
                          color: txn.type === 'income' ? 'var(--success)' : 'var(--text-primary)'
                        }}
                      >
                        {txn.type === 'income' ? '+' : '-'}
                        {formatCurrency(txn.amount, currency)}
                      </td>
                      <td style={{ textAlign: 'center', whiteSpace: 'nowrap' }}>
                        <button
                          className="btn btn-outline btn-sm"
                          style={{ padding: '4px 8px', marginRight: 6 }}
                          onClick={() => setEditingTxn({ ...txn })}
                        >
                          <Edit2 size={13} />
                        </button>
                        <button
                          className="btn btn-outline btn-sm"
                          style={{ padding: '4px 8px', color: 'var(--danger)' }}
                          onClick={() => {
                            if (window.confirm(`Delete "${txn.description}"?`)) {
                              deleteTransaction(txn.id);
                            }
                          }}
                        >
                          <Trash2 size={13} />
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination controls */}
        {totalPages > 1 && (
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              marginTop: 20,
              paddingTop: 16,
              borderTop: '1px solid var(--border-color)'
            }}
          >
            <button
              className="btn btn-outline btn-sm"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              &larr; Previous
            </button>
            <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
              Page {page} of {totalPages} ({filtered.length} total)
            </span>
            <button
              className="btn btn-outline btn-sm"
              disabled={page >= totalPages}
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
            >
              Next &rarr;
            </button>
          </div>
        )}
      </div>

      {/* Edit Transaction Modal */}
      {editingTxn && (
        <div className="modal-backdrop" onClick={() => setEditingTxn(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3 className="modal-title">Edit Transaction</h3>
              <button className="modal-close" onClick={() => setEditingTxn(null)}>
                &times;
              </button>
            </div>
            <form onSubmit={handleSaveEdit}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">Type</label>
                  <select
                    className="form-select"
                    value={editingTxn.type}
                    onChange={(e) => setEditingTxn({ ...editingTxn, type: e.target.value })}
                  >
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Amount ({currency})</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    className="form-control"
                    value={editingTxn.amount}
                    onChange={(e) => setEditingTxn({ ...editingTxn, amount: e.target.value })}
                    required
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Description</label>
                  <input
                    type="text"
                    className="form-control"
                    value={editingTxn.description}
                    onChange={(e) => setEditingTxn({ ...editingTxn, description: e.target.value })}
                    required
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                  <div className="form-group">
                    <label className="form-label">Category</label>
                    <select
                      className="form-select"
                      value={editingTxn.category_id}
                      onChange={(e) => setEditingTxn({ ...editingTxn, category_id: Number(e.target.value) })}
                    >
                      {categories
                        .filter((c) => c.type === editingTxn.type)
                        .map((c) => (
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
                      value={editingTxn.date}
                      onChange={(e) => setEditingTxn({ ...editingTxn, date: e.target.value })}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Payment Method</label>
                  <select
                    className="form-select"
                    value={editingTxn.payment_method}
                    onChange={(e) => setEditingTxn({ ...editingTxn, payment_method: e.target.value })}
                  >
                    <option value="upi">UPI</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="debit_card">Debit Card</option>
                    <option value="cash">Cash</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Notes</label>
                  <textarea
                    className="form-control"
                    rows="2"
                    value={editingTxn.notes || ''}
                    onChange={(e) => setEditingTxn({ ...editingTxn, notes: e.target.value })}
                  />
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline" onClick={() => setEditingTxn(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
