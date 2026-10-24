import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import { 
  Sparkles, 
  ArrowRight, 
  ArrowLeft, 
  Check, 
  User, 
  Wallet, 
  Home, 
  Target, 
  ShieldCheck,
  TrendingUp,
  AlertCircle
} from 'lucide-react';

export default function OnboardingView() {
  const { user, settings, updateSettings, addBill, addSavingsGoal, setActiveView, currencySymbol } = useApp();

  const [step, setStep] = useState(1);

  // Step 1: Profile
  const [profile, setProfile] = useState({
    name: user?.name || 'Kiran Kumar',
    email: user?.email || 'kiran@example.com',
    currency: settings?.currency || 'INR',
    opening_balance: settings?.opening_balance || 42000
  });

  // Step 2: Income
  const [income, setIncome] = useState({
    source: 'Salaried Employee',
    monthly_amount: settings?.monthly_income || 65000,
    salary_day: settings?.salary_day || 1,
    is_variable: false
  });

  // Step 3: Commitments
  const [commitments, setCommitments] = useState({
    rent: 14000,
    groceries: 7500,
    utilities: 2800,
    wifi_mobile: 1200,
    transport: 3000,
    loans_emi: 0
  });

  // Step 4: Goals
  const [goals, setGoals] = useState({
    primary_goal: 'Emergency Safety Fund',
    target_amount: 180000,
    monthly_savings: settings?.planned_savings || 15000,
    target_months: 12
  });

  // Calculations for Step 5
  const totalCommitments = Object.values(commitments).reduce((sum, v) => sum + Number(v || 0), 0);
  const totalIncome = Number(income.monthly_amount || 0);
  const plannedSavings = Number(goals.monthly_savings || 0);
  const calculatedFlexible = Math.max(0, totalIncome - totalCommitments - plannedSavings);
  const dailyAllowance = Math.round(calculatedFlexible / 30);

  const handleFinish = () => {
    // 1. Update Core Settings
    updateSettings(
      {
        currency: profile.currency,
        monthly_income: totalIncome,
        salary_day: Number(income.salary_day),
        planned_savings: plannedSavings,
        opening_balance: Number(profile.opening_balance)
      },
      {
        name: profile.name,
        email: profile.email,
        occupation: income.source
      }
    );

    // 2. If commitments exist, add sample recurring bill if not already present
    if (commitments.rent > 0) {
      addBill({
        name: 'House Rent',
        category_id: 1, // Housing & Rent
        amount: Number(commitments.rent),
        frequency: 'monthly',
        due_day: Number(income.salary_day) <= 5 ? 5 : Number(income.salary_day),
        next_due_date: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 5).toISOString().split('T')[0],
        notes: 'Configured during initial financial onboarding'
      });
    }

    if (commitments.wifi_mobile > 0) {
      addBill({
        name: 'Fiber Broadband & Mobile',
        category_id: 5, // Utilities
        amount: Number(commitments.wifi_mobile),
        frequency: 'monthly',
        due_day: 10,
        next_due_date: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 10).toISOString().split('T')[0],
        notes: 'High speed internet connection'
      });
    }

    // 3. Add configured savings goal
    if (goals.target_amount > 0) {
      const targetDate = new Date();
      targetDate.setMonth(targetDate.getMonth() + Number(goals.target_months || 12));
      addSavingsGoal({
        name: goals.primary_goal,
        target_amount: Number(goals.target_amount),
        current_amount: Math.round(Number(goals.target_amount) * 0.1), // 10% seeded
        target_date: targetDate.toISOString().split('T')[0],
        priority: 'high',
        category: 'Emergency Fund'
      });
    }

    // Navigate to Dashboard
    setActiveView('dashboard');
  };

  return (
    <div className="max-w-3xl mx-auto py-6">
      {/* Wizard Header */}
      <div className="text-center mb-8">
        <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-semibold mb-3">
          <Sparkles size={14} />
          <span>Interactive Financial Onboarding</span>
        </div>
        <h2 className="text-3xl font-extrabold text-white">
          Welcome to SmartSpend
        </h2>
        <p className="text-sm text-slate-400 mt-2">
          Let's tailor your smart salary capacity equation and build your personalized financial plan in 5 quick steps.
        </p>

        {/* Step Indicator */}
        <div className="flex items-center justify-center gap-2 mt-6">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="flex items-center">
              <div 
                className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                  step === i 
                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' 
                    : step > i 
                    ? 'bg-emerald-600 text-white' 
                    : 'bg-slate-800 text-slate-500 border border-slate-700'
                }`}
              >
                {step > i ? <Check size={14} /> : i}
              </div>
              {i < 5 && (
                <div className={`w-8 sm:w-12 h-1 mx-1 rounded-full ${step > i ? 'bg-emerald-600' : 'bg-slate-800'}`} />
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Step Content Box */}
      <div className="card p-6 sm:p-8">
        {/* Step 1: Personal Info & Currency */}
        {step === 1 && (
          <div className="space-y-5">
            <div className="border-b border-slate-800 pb-4">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <User size={20} className="text-indigo-400" />
                Step 1: Personal Profile & Base Currency
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Configure your display name and cash baseline.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Full Name
                </label>
                <input
                  type="text"
                  required
                  value={profile.name}
                  onChange={e => setProfile({ ...profile, name: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Email Address
                </label>
                <input
                  type="email"
                  required
                  value={profile.email}
                  onChange={e => setProfile({ ...profile, email: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Preferred Currency
                </label>
                <select
                  value={profile.currency}
                  onChange={e => setProfile({ ...profile, currency: e.target.value })}
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
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Recorded Opening Reserve ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="500"
                  value={profile.opening_balance}
                  onChange={e => setProfile({ ...profile, opening_balance: e.target.value })}
                  className="input w-full font-semibold"
                />
                <span className="text-[11px] text-slate-400">Total liquid balance currently in bank accounts & wallet</span>
              </div>
            </div>
          </div>
        )}

        {/* Step 2: Income Information */}
        {step === 2 && (
          <div className="space-y-5">
            <div className="border-b border-slate-800 pb-4">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Wallet size={20} className="text-emerald-400" />
                Step 2: Income Stream & Salary Schedule
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Tell us about your earnings so the capacity planner knows your monthly inflow volume.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Primary Income Source
                </label>
                <select
                  value={income.source}
                  onChange={e => setIncome({ ...income, source: e.target.value })}
                  className="input w-full cursor-pointer"
                >
                  <option value="Salaried Employee">Salaried Employee</option>
                  <option value="Freelancer / Contractor">Freelancer / Contractor</option>
                  <option value="Business Owner">Business Owner</option>
                  <option value="Student">Student (Stipend / Allowance)</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Monthly Take-Home Inflow ({currencySymbol}) *
                </label>
                <input
                  type="number"
                  min="1000"
                  step="500"
                  required
                  value={income.monthly_amount}
                  onChange={e => setIncome({ ...income, monthly_amount: e.target.value })}
                  className="input w-full font-bold text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Salary Credit Day of Month (1–31)
                </label>
                <input
                  type="number"
                  min="1"
                  max="31"
                  value={income.salary_day}
                  onChange={e => setIncome({ ...income, salary_day: e.target.value })}
                  className="input w-full font-semibold"
                />
                <span className="text-[11px] text-slate-400">Day when your primary paycheck lands in your account</span>
              </div>

              <div className="flex items-center">
                <label className="p-3 rounded-lg bg-slate-800/80 border border-slate-700 w-full flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={income.is_variable}
                    onChange={e => setIncome({ ...income, is_variable: e.target.checked })}
                    className="rounded border-slate-700 text-indigo-600 focus:ring-0"
                  />
                  <div>
                    <span className="text-xs font-semibold text-white block">Variable / Fluctuating Income</span>
                    <span className="text-[11px] text-slate-400">SmartSpend will use a conservative baseline for safety</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        )}

        {/* Step 3: Monthly Non-Negotiable Commitments */}
        {step === 3 && (
          <div className="space-y-5">
            <div className="border-b border-slate-800 pb-4">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Home size={20} className="text-rose-400" />
                Step 3: Baseline Monthly Living Commitments
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Enter your fixed and essential monthly expenses. These are non-negotiable needs.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Rent / Housing ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="500"
                  value={commitments.rent}
                  onChange={e => setCommitments({ ...commitments, rent: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Groceries & Food ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="500"
                  value={commitments.groceries}
                  onChange={e => setCommitments({ ...commitments, groceries: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Electricity & Utilities ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="100"
                  value={commitments.utilities}
                  onChange={e => setCommitments({ ...commitments, utilities: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  WiFi & Mobile ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="100"
                  value={commitments.wifi_mobile}
                  onChange={e => setCommitments({ ...commitments, wifi_mobile: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Daily Commute ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="200"
                  value={commitments.transport}
                  onChange={e => setCommitments({ ...commitments, transport: e.target.value })}
                  className="input w-full"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Loans & EMI ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="0"
                  step="500"
                  value={commitments.loans_emi}
                  onChange={e => setCommitments({ ...commitments, loans_emi: e.target.value })}
                  className="input w-full"
                />
              </div>
            </div>

            <div className="p-3.5 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-center justify-between text-xs">
              <span className="text-slate-400 font-medium">Total Monthly Essential Commitments:</span>
              <span className="font-bold text-rose-400 text-sm">
                {formatCurrency(totalCommitments, currencySymbol)}
              </span>
            </div>
          </div>
        )}

        {/* Step 4: Financial Goals */}
        {step === 4 && (
          <div className="space-y-5">
            <div className="border-b border-slate-800 pb-4">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Target size={20} className="text-cyan-400" />
                Step 4: Savings Ambitions & Goals
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Decide how much you want to pay your future self before allocating discretionary spending.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Primary Savings Purpose
                </label>
                <select
                  value={goals.primary_goal}
                  onChange={e => setGoals({ ...goals, primary_goal: e.target.value })}
                  className="input w-full cursor-pointer"
                >
                  <option value="Emergency Safety Fund">Emergency Safety Fund</option>
                  <option value="MacBook / Tech Upgrade">MacBook / Tech Upgrade</option>
                  <option value="Vacation & Travel">Vacation & Travel</option>
                  <option value="Debt Payoff">Debt Payoff</option>
                  <option value="Wealth & Mutual Funds">Wealth & Mutual Funds</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Monthly Savings Target ({currencySymbol}) *
                </label>
                <input
                  type="number"
                  min="0"
                  step="500"
                  required
                  value={goals.monthly_savings}
                  onChange={e => setGoals({ ...goals, monthly_savings: e.target.value })}
                  className="input w-full font-bold text-cyan-400"
                />
                <span className="text-[11px] text-slate-400">
                  Recommended: 20% of monthly income (~{formatCurrency(Math.round(totalIncome * 0.2), currencySymbol)})
                </span>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Total Target Amount ({currencySymbol})
                </label>
                <input
                  type="number"
                  min="1000"
                  step="1000"
                  value={goals.target_amount}
                  onChange={e => setGoals({ ...goals, target_amount: e.target.value })}
                  className="input w-full font-semibold"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                  Target Timeline (Months)
                </label>
                <input
                  type="number"
                  min="1"
                  max="60"
                  value={goals.target_months}
                  onChange={e => setGoals({ ...goals, target_months: e.target.value })}
                  className="input w-full font-semibold"
                />
              </div>
            </div>
          </div>
        )}

        {/* Step 5: Plan Summary & Confirmation */}
        {step === 5 && (
          <div className="space-y-6">
            <div className="border-b border-slate-800 pb-4">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <ShieldCheck size={20} className="text-emerald-400" />
                Step 5: Personalized Salary Capacity Plan Preview
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Here is your transparent cash allocation plan calculated by SmartSpend's formula:
              </p>
            </div>

            {/* Capacity Equation Flow */}
            <div className="p-4 rounded-xl bg-slate-850 border border-slate-800 space-y-3">
              <div className="flex items-center justify-between text-sm py-1.5 border-b border-slate-800">
                <span className="text-slate-300">1. Monthly Take-Home Inflow</span>
                <span className="font-bold text-emerald-400">+{formatCurrency(totalIncome, currencySymbol)}</span>
              </div>
              <div className="flex items-center justify-between text-sm py-1.5 border-b border-slate-800">
                <span className="text-slate-300">2. Non-Negotiable Living Commitments</span>
                <span className="font-bold text-rose-400">-{formatCurrency(totalCommitments, currencySymbol)}</span>
              </div>
              <div className="flex items-center justify-between text-sm py-1.5 border-b border-slate-800">
                <span className="text-slate-300">3. Planned Savings Target (Pay Yourself First)</span>
                <span className="font-bold text-cyan-400">-{formatCurrency(plannedSavings, currencySymbol)}</span>
              </div>
              <div className="flex items-center justify-between text-base py-2 font-bold bg-slate-800/60 px-3 rounded-lg">
                <span className="text-white">= Monthly Flexible Discretionary Budget:</span>
                <span className="text-indigo-400 text-lg">
                  {formatCurrency(calculatedFlexible, currencySymbol)}
                </span>
              </div>
            </div>

            {/* Daily Allowance Highlight */}
            <div className="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-between">
              <div>
                <span className="text-xs uppercase font-semibold text-indigo-300">Safe Daily Discretionary Spending Allowance</span>
                <h4 className="text-2xl font-bold text-white mt-1">
                  {formatCurrency(dailyAllowance, currencySymbol)} <span className="text-xs font-normal text-slate-400">/ day</span>
                </h4>
              </div>
              <div className="text-right text-xs text-slate-400 max-w-[200px]">
                Stay under this daily allowance on shopping and dining to effortlessly reach your goals.
              </div>
            </div>
          </div>
        )}

        {/* Wizard Navigation Buttons */}
        <div className="mt-8 pt-4 border-t border-slate-800 flex items-center justify-between">
          {step > 1 ? (
            <button
              type="button"
              onClick={() => setStep(step - 1)}
              className="btn btn-secondary text-xs flex items-center gap-1.5"
            >
              <ArrowLeft size={14} />
              <span>Previous</span>
            </button>
          ) : (
            <div />
          )}

          {step < 5 ? (
            <button
              type="button"
              onClick={() => setStep(step + 1)}
              className="btn btn-primary text-xs flex items-center gap-1.5"
            >
              <span>Continue</span>
              <ArrowRight size={14} />
            </button>
          ) : (
            <button
              type="button"
              onClick={handleFinish}
              className="btn btn-primary text-xs flex items-center gap-1.5 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 font-bold"
            >
              <Check size={16} />
              <span>Confirm & Launch SmartSpend</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
