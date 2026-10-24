import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Settings as SettingsIcon, 
  User, 
  Wallet, 
  RotateCcw, 
  Check, 
  Sparkles, 
  AlertTriangle,
  Compass,
  DollarSign
} from 'lucide-react';

export default function SettingsView() {
  const { user, settings, updateSettings, resetToDemoData, setActiveView } = useApp();

  const [formData, setFormData] = useState({
    name: user?.name || 'Kiran Kumar',
    email: user?.email || 'kiran@example.com',
    currency: settings?.currency || 'INR',
    monthly_income: settings?.monthly_income || 65000,
    salary_day: settings?.salary_day || 1,
    planned_savings: settings?.planned_savings || 15000,
    opening_balance: settings?.opening_balance || 42000,
    occupation: user?.occupation || 'Software Engineer'
  });

  const [savedSuccess, setSavedSuccess] = useState(false);

  const handleSubmit = (e) => {
    e.preventDefault();
    updateSettings(
      {
        currency: formData.currency,
        monthly_income: Number(formData.monthly_income),
        salary_day: Number(formData.salary_day),
        planned_savings: Number(formData.planned_savings),
        opening_balance: Number(formData.opening_balance)
      },
      {
        name: formData.name,
        email: formData.email,
        occupation: formData.occupation
      }
    );

    setSavedSuccess(true);
    setTimeout(() => setSavedSuccess(false), 3000);
  };

  const handleResetDemo = () => {
    if (confirm('Reset application to comprehensive initial demo data with sample transactions, recurring bills, and savings goals?')) {
      resetToDemoData();
      alert('Demo data successfully reloaded!');
    }
  };

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-white flex items-center gap-2">
          <SettingsIcon className="text-indigo-400" size={26} />
          Account & Financial Engine Settings
        </h2>
        <p className="text-sm text-slate-400 mt-1">
          Customize your profile, configure your income schedule, and calibrate your smart salary capacity equation.
        </p>
      </div>

      {savedSuccess && (
        <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center gap-2">
          <Check size={18} />
          <span>Your settings and financial engine parameters have been successfully updated!</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Profile Section */}
        <div className="card p-6 space-y-4">
          <h3 className="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
            <User size={18} className="text-indigo-400" />
            Personal Profile
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Full Name
              </label>
              <input
                type="text"
                required
                value={formData.name}
                onChange={e => setFormData({ ...formData, name: e.target.value })}
                className="input w-full"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Email Address
              </label>
              <input
                type="email"
                required
                value={formData.email}
                onChange={e => setFormData({ ...formData, email: e.target.value })}
                className="input w-full"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Occupation / Role
              </label>
              <input
                type="text"
                value={formData.occupation}
                onChange={e => setFormData({ ...formData, occupation: e.target.value })}
                placeholder="e.g. Salaried, Freelancer, Student"
                className="input w-full"
              />
            </div>
          </div>
        </div>

        {/* Financial Engine Parameters */}
        <div className="card p-6 space-y-4">
          <h3 className="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
            <Wallet size={18} className="text-emerald-400" />
            Smart Salary & Capacity Engine Parameters
          </h3>
          <p className="text-xs text-slate-400">
            These figures directly calibrate your live discretionary allowance formula:
            <br />
            <code className="text-indigo-300 font-mono">Flexible Capacity = Monthly Income - Essential Commitments - Unpaid Bills - Planned Savings Target</code>
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Preferred Currency
              </label>
              <select
                value={formData.currency}
                onChange={e => setFormData({ ...formData, currency: e.target.value })}
                className="input w-full cursor-pointer"
              >
                <option value="INR">₹ INR (Indian Rupee)</option>
                <option value="USD">$ USD (US Dollar)</option>
                <option value="EUR">€ EUR (Euro)</option>
                <option value="GBP">£ GBP (British Pound)</option>
                <option value="AED">AED (UAE Dirham)</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Monthly Take-Home Salary
              </label>
              <input
                type="number"
                min="0"
                step="500"
                required
                value={formData.monthly_income}
                onChange={e => setFormData({ ...formData, monthly_income: e.target.value })}
                className="input w-full font-semibold"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Salary Credit Day of Month
              </label>
              <input
                type="number"
                min="1"
                max="31"
                required
                value={formData.salary_day}
                onChange={e => setFormData({ ...formData, salary_day: e.target.value })}
                className="input w-full font-semibold"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Monthly Target Savings Target
              </label>
              <input
                type="number"
                min="0"
                step="500"
                required
                value={formData.planned_savings}
                onChange={e => setFormData({ ...formData, planned_savings: e.target.value })}
                className="input w-full font-semibold"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 uppercase mb-1">
                Recorded Opening Reserve
              </label>
              <input
                type="number"
                min="0"
                step="1000"
                required
                value={formData.opening_balance}
                onChange={e => setFormData({ ...formData, opening_balance: e.target.value })}
                className="input w-full font-semibold"
              />
            </div>
          </div>

          <div className="pt-2">
            <button
              type="submit"
              className="btn btn-primary text-xs px-6 py-2.5 font-semibold"
            >
              Save Financial Parameters
            </button>
          </div>
        </div>
      </form>

      {/* Guided Setup & Demo Maintenance */}
      <div className="card p-6 space-y-4">
        <h3 className="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
          <Compass size={18} className="text-amber-400" />
          Onboarding & System Maintenance
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60 flex flex-col justify-between">
            <div>
              <h4 className="text-sm font-semibold text-white">Financial Onboarding Wizard</h4>
              <p className="text-xs text-slate-400 mt-1">
                Re-run the 5-step interactive financial onboarding wizard to re-estimate your baseline commitments and savings goals.
              </p>
            </div>
            <button
              type="button"
              onClick={() => setActiveView('onboarding')}
              className="btn btn-secondary text-xs mt-4 self-start"
            >
              Launch Onboarding Wizard
            </button>
          </div>

          <div className="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60 flex flex-col justify-between">
            <div>
              <h4 className="text-sm font-semibold text-white">Reset to Demo Data</h4>
              <p className="text-xs text-slate-400 mt-1">
                Reload the comprehensive portfolio dataset with realistic Indian expense transactions, recurring utility bills, and savings milestones.
              </p>
            </div>
            <button
              type="button"
              onClick={handleResetDemo}
              className="btn btn-secondary text-xs mt-4 text-amber-400 hover:text-amber-300 self-start flex items-center gap-1.5"
            >
              <RotateCcw size={14} />
              <span>Reset Sample Data</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
