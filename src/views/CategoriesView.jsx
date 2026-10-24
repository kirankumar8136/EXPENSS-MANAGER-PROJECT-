import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Tags, 
  Plus, 
  Trash2, 
  CheckCircle, 
  AlertCircle, 
  Lock,
  Utensils,
  Zap,
  Bus,
  Film,
  Shield,
  ShoppingBag,
  Coffee,
  HeartPulse,
  Laptop,
  Briefcase,
  DollarSign,
  Sparkles,
  Award
} from 'lucide-react';

const PRESET_COLORS = [
  '#ef4444', '#f97316', '#f59e0b', '#10b981', 
  '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', 
  '#ec4899', '#64748b'
];

export default function CategoriesView() {
  const { categories, transactions, addCategory, deleteCategory } = useApp();
  const [activeTab, setActiveTab] = useState('expense');
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form State
  const [name, setName] = useState('');
  const [type, setType] = useState('expense');
  const [isEssential, setIsEssential] = useState(false);
  const [color, setColor] = useState('#6366f1');
  const [icon, setIcon] = useState('Tags');

  const filteredCategories = categories.filter(c => c.type === activeTab);

  // Count transactions per category
  const getTxnCount = (categoryId) => {
    return transactions.filter(t => t.category_id === categoryId).length;
  };

  const handleCreate = (e) => {
    e.preventDefault();
    if (!name.trim()) return;

    addCategory({
      name: name.trim(),
      type,
      is_essential: type === 'expense' ? isEssential : false,
      color,
      icon
    });

    // Reset and close
    setName('');
    setIsEssential(false);
    setColor('#6366f1');
    setIsModalOpen(false);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-white flex items-center gap-2">
            <Tags className="text-indigo-400" size={26} />
            Categories Management
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            Organize transactions and mark essential living expenses to automate capacity planning.
          </p>
        </div>
        <button
          onClick={() => {
            setType(activeTab);
            setIsModalOpen(true);
          }}
          className="btn btn-primary text-xs flex items-center gap-1.5 self-start sm:self-auto"
        >
          <Plus size={16} />
          <span>Add Custom Category</span>
        </button>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-3 border-b border-slate-800 pb-3">
        <button
          onClick={() => setActiveTab('expense')}
          className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${
            activeTab === 'expense'
              ? 'bg-rose-500/10 text-rose-400 border border-rose-500/30'
              : 'text-slate-400 hover:text-white hover:bg-slate-800'
          }`}
        >
          Expense Categories ({categories.filter(c => c.type === 'expense').length})
        </button>
        <button
          onClick={() => setActiveTab('income')}
          className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${
            activeTab === 'income'
              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
              : 'text-slate-400 hover:text-white hover:bg-slate-800'
          }`}
        >
          Income Categories ({categories.filter(c => c.type === 'income').length})
        </button>
      </div>

      {/* Categories Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {filteredCategories.map(cat => {
          const txnCount = getTxnCount(cat.id);
          return (
            <div 
              key={cat.id} 
              className="card p-4 flex flex-col justify-between border-t-2"
              style={{ borderTopColor: cat.color || '#6366f1' }}
            >
              <div>
                <div className="flex items-center justify-between mb-3">
                  <div 
                    className="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-white shadow"
                    style={{ backgroundColor: `${cat.color || '#6366f1'}33`, color: cat.color || '#6366f1' }}
                  >
                    <Tags size={20} />
                  </div>
                  {cat.is_system ? (
                    <span 
                      className="text-slate-500 flex items-center gap-1 text-[11px]" 
                      title="System Default Category (Protected)"
                    >
                      <Lock size={12} />
                      System
                    </span>
                  ) : (
                    <button
                      onClick={() => {
                        if (confirm(`Delete custom category "${cat.name}"?`)) {
                          deleteCategory(cat.id);
                        }
                      }}
                      className="text-slate-500 hover:text-rose-400 p-1 rounded hover:bg-slate-800 transition-colors"
                      title="Delete Custom Category"
                    >
                      <Trash2 size={15} />
                    </button>
                  )}
                </div>

                <h4 className="font-semibold text-white text-base">{cat.name}</h4>

                {cat.type === 'expense' && (
                  <div className="mt-2">
                    {cat.is_essential ? (
                      <span className="badge badge-info text-[11px] font-medium">
                        Essential Need
                      </span>
                    ) : (
                      <span className="badge badge-secondary text-[11px] font-medium">
                        Discretionary
                      </span>
                    )}
                  </div>
                )}
              </div>

              <div className="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>Usage</span>
                <span className="font-semibold text-slate-300">{txnCount} transactions</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Add Custom Category Modal */}
      {isModalOpen && (
        <div className="modal-overlay" onClick={() => setIsModalOpen(false)}>
          <div className="modal-container max-w-md" onClick={e => e.stopPropagation()}>
            <div className="p-5 border-b border-slate-800 flex items-center justify-between">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Tags className="text-indigo-400" size={20} />
                Create Custom Category
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-white text-xl"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleCreate} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Category Name *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Pet Care, Gaming, Side Hustle"
                  value={name}
                  onChange={e => setName(e.target.value)}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Category Type *
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setType('expense')}
                    className={`py-2 rounded-lg text-xs font-semibold border ${
                      type === 'expense'
                        ? 'bg-rose-500/20 border-rose-500 text-rose-300'
                        : 'bg-slate-800/80 border-slate-700 text-slate-400'
                    }`}
                  >
                    Expense
                  </button>
                  <button
                    type="button"
                    onClick={() => setType('income')}
                    className={`py-2 rounded-lg text-xs font-semibold border ${
                      type === 'income'
                        ? 'bg-emerald-500/20 border-emerald-500 text-emerald-300'
                        : 'bg-slate-800/80 border-slate-700 text-slate-400'
                    }`}
                  >
                    Income
                  </button>
                </div>
              </div>

              {type === 'expense' && (
                <div className="p-3 rounded-lg bg-slate-800/70 border border-slate-700/60">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={isEssential}
                      onChange={e => setIsEssential(e.target.checked)}
                      className="rounded border-slate-700 text-indigo-600 focus:ring-0"
                    />
                    <span className="text-xs font-medium text-white">Mark as Non-Negotiable Essential Living Need</span>
                  </label>
                  <p className="text-[11px] text-slate-400 mt-1 pl-6">
                    Essential expenses are subtracted first during salary capacity planning to safeguard food, rent, and utility commitments.
                  </p>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-2">
                  Theme Color
                </label>
                <div className="flex items-center gap-2 flex-wrap">
                  {PRESET_COLORS.map(c => (
                    <button
                      key={c}
                      type="button"
                      onClick={() => setColor(c)}
                      className={`w-7 h-7 rounded-full border-2 transition-transform ${
                        color === c ? 'scale-110 border-white shadow-lg' : 'border-transparent'
                      }`}
                      style={{ backgroundColor: c }}
                    />
                  ))}
                </div>
              </div>

              <div className="pt-4 border-t border-slate-800 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="btn btn-secondary text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="btn btn-primary text-xs"
                >
                  Create Category
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
