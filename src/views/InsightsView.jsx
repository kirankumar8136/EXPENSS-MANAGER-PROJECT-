import React from 'react';
import { useApp } from '../context/AppContext';
import { generateFinancialInsights } from '../services/insightsEngine';
import { formatCurrency } from '../services/calculations';
import { 
  Lightbulb, 
  TrendingUp, 
  TrendingDown, 
  AlertTriangle, 
  Compass, 
  Tv, 
  ArrowRight, 
  CheckCircle2, 
  ShieldAlert, 
  Zap,
  Calendar
} from 'lucide-react';

export default function InsightsView() {
  const { 
    transactions, 
    categories, 
    bills, 
    capacityPlan, 
    currencySymbol, 
    setActiveView 
  } = useApp();

  const insights = generateFinancialInsights({
    allTransactions: transactions,
    categories,
    recurringBills: bills,
    capacityPlan,
    currency: currencySymbol
  });

  // Calculate Apples-to-Apples Category Comparison
  const now = new Date();
  const dayOfMonth = now.getDate();
  const curYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const prevYm = `${prevMonthDate.getFullYear()}-${String(prevMonthDate.getMonth() + 1).padStart(2, '0')}`;
  const curDayStr = `${curYm}-${String(dayOfMonth).padStart(2, '0')}`;
  const prevDayStr = `${prevYm}-${String(dayOfMonth).padStart(2, '0')}`;

  const categoryComparison = categories
    .filter(c => c.type === 'expense')
    .map(c => {
      let cur = 0;
      let prev = 0;
      transactions.forEach(t => {
        if (t.type === 'expense' && t.category_id === c.id) {
          if (t.date >= `${curYm}-01` && t.date <= curDayStr) {
            cur += Number(t.amount || 0);
          } else if (t.date >= `${prevYm}-01` && t.date <= prevDayStr) {
            prev += Number(t.amount || 0);
          }
        }
      });
      const diff = cur - prev;
      const pct = prev > 0 ? Math.round((diff / prev) * 100) : (cur > 0 ? 100 : 0);
      return {
        ...c,
        cur,
        prev,
        diff,
        pct,
        isSurge: pct >= 20 && diff >= 400
      };
    })
    .filter(item => item.cur > 0 || item.prev > 0)
    .sort((a, b) => b.diff - a.diff);

  // Subscriptions breakdown
  const subs = bills.filter(
    b => b.status === 'active' &&
    (b.name.toLowerCase().includes('netflix') ||
     b.name.toLowerCase().includes('spotify') ||
     b.name.toLowerCase().includes('prime') ||
     b.name.toLowerCase().includes('subscription') ||
     b.name.toLowerCase().includes('hotstar') ||
     b.name.toLowerCase().includes('gym') ||
     b.category === 'Entertainment')
  );
  const monthlySubTotal = subs.reduce((sum, s) => sum + Number(s.amount || 0), 0);
  const annualSubTotal = monthlySubTotal * 12;

  const renderIcon = (iconName, type) => {
    const props = { size: 22 };
    switch (iconName) {
      case 'AlertTriangle':
        return <AlertTriangle {...props} className="text-rose-400" />;
      case 'Compass':
        return <Compass {...props} className="text-cyan-400" />;
      case 'TrendingUp':
        return <TrendingUp {...props} className="text-amber-400" />;
      case 'Tv':
        return <Tv {...props} className="text-indigo-400" />;
      default:
        return <Lightbulb {...props} className="text-emerald-400" />;
    }
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-white flex items-center gap-2">
            <Zap className="text-amber-400" size={26} />
            Financial Insights & Behavioral Analytics
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            Deterministic, rule-based intelligence analyzing your income trajectory, discretionary burn rates, and spending surges.
          </p>
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700 text-xs text-slate-300">
          <Calendar size={14} className="text-slate-400" />
          <span>Comparing Day 1–{dayOfMonth} against previous month</span>
        </div>
      </div>

      {/* 4 Overview Mini Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="card p-4">
          <span className="text-xs text-slate-400 uppercase font-semibold">Remaining Flexible Spending</span>
          <div className="text-xl font-bold text-white mt-1">
            {formatCurrency(capacityPlan.remainingFlexible, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            {capacityPlan.remainingDays} days remaining this cycle
          </p>
        </div>

        <div className="card p-4">
          <span className="text-xs text-slate-400 uppercase font-semibold">Safe Daily Allowance</span>
          <div className="text-xl font-bold text-cyan-400 mt-1">
            {formatCurrency(capacityPlan.dailyDiscretionary, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Target daily ceiling to stay in budget
          </p>
        </div>

        <div className="card p-4">
          <span className="text-xs text-slate-400 uppercase font-semibold">Annual Subscriptions Burden</span>
          <div className="text-xl font-bold text-amber-400 mt-1">
            {formatCurrency(annualSubTotal, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            {subs.length} active recurring commitments
          </p>
        </div>

        <div className="card p-4">
          <span className="text-xs text-slate-400 uppercase font-semibold">Surging Categories</span>
          <div className="text-xl font-bold text-rose-400 mt-1">
            {categoryComparison.filter(c => c.isSurge).length} Categories
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Spending up &gt; 20% vs prior month pace
          </p>
        </div>
      </div>

      {/* Dynamic Actionable Insights */}
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-white flex items-center gap-2">
          <Lightbulb size={20} className="text-amber-400" />
          Actionable Recommendations
        </h3>
        {insights.length === 0 ? (
          <div className="card p-8 text-center text-slate-400">
            <CheckCircle2 size={40} className="mx-auto text-emerald-400 mb-2 opacity-80" />
            <p className="text-white font-medium">Your finances are on track!</p>
            <p className="text-xs mt-1">No anomalous surges or discretionary budget deficits detected at this time.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {insights.map((item, idx) => (
              <div 
                key={idx} 
                className={`card p-5 border-l-4 flex flex-col justify-between ${
                  item.type === 'danger' ? 'border-l-rose-500 bg-rose-950/20' :
                  item.type === 'warning' ? 'border-l-amber-500 bg-amber-950/20' :
                  item.type === 'info' ? 'border-l-cyan-500 bg-cyan-950/20' :
                  'border-l-emerald-500 bg-emerald-950/20'
                }`}
              >
                <div>
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-slate-800/80 border border-slate-700/50">
                      {renderIcon(item.icon, item.type)}
                    </div>
                    <h4 className="font-semibold text-white text-base">{item.title}</h4>
                  </div>
                  <p className="text-sm text-slate-300 mt-3 leading-relaxed">
                    {item.description}
                  </p>
                </div>
                {item.actionText && (
                  <button
                    onClick={() => item.actionView && setActiveView(item.actionView)}
                    className="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition-colors pt-3 border-t border-slate-800/60"
                  >
                    <span>{item.actionText}</span>
                    <ArrowRight size={14} />
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Apples-to-Apples Comparison Grid */}
      <div className="card overflow-hidden">
        <div className="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 className="text-base font-semibold text-white">Apples-to-Apples Spending Velocity Comparison</h3>
            <p className="text-xs text-slate-400 mt-0.5">
              Normalizes for calendar day: Compares Day 1–{dayOfMonth} of {now.toLocaleString('default', { month: 'long' })} with Day 1–{dayOfMonth} of previous month.
            </p>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-800/60 text-xs font-semibold text-slate-400 uppercase tracking-wider">
              <tr>
                <th className="py-3 px-4">Category</th>
                <th className="py-3 px-4">This Month (Day 1–{dayOfMonth})</th>
                <th className="py-3 px-4">Last Month (Day 1–{dayOfMonth})</th>
                <th className="py-3 px-4">Variance</th>
                <th className="py-3 px-4">Pace Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-300">
              {categoryComparison.map(cat => (
                <tr key={cat.id} className="hover:bg-slate-800/40 transition-colors">
                  <td className="py-3.5 px-4 font-medium text-white flex items-center gap-2">
                    <span 
                      className="w-2.5 h-2.5 rounded-full inline-block" 
                      style={{ backgroundColor: cat.color || '#6366f1' }}
                    />
                    {cat.name}
                  </td>
                  <td className="py-3.5 px-4 font-semibold text-white">
                    {formatCurrency(cat.cur, currencySymbol)}
                  </td>
                  <td className="py-3.5 px-4 text-slate-400">
                    {formatCurrency(cat.prev, currencySymbol)}
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`inline-flex items-center gap-1 font-semibold text-xs ${
                      cat.diff > 0 ? 'text-rose-400' : cat.diff < 0 ? 'text-emerald-400' : 'text-slate-400'
                    }`}>
                      {cat.diff > 0 ? '+' : ''}{formatCurrency(cat.diff, currencySymbol)}
                      {cat.prev > 0 && ` (${cat.pct > 0 ? '+' : ''}${cat.pct}%)`}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    {cat.isSurge ? (
                      <span className="badge badge-danger flex items-center gap-1 w-max">
                        <TrendingUp size={12} />
                        Surge Alert (+{cat.pct}%)
                      </span>
                    ) : cat.diff < 0 ? (
                      <span className="badge badge-success flex items-center gap-1 w-max">
                        <TrendingDown size={12} />
                        Reduced Spending
                      </span>
                    ) : (
                      <span className="badge badge-secondary w-max">
                        Normal Pace
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Subscription Audit */}
      <div className="card p-5">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
          <div>
            <h3 className="text-base font-semibold text-white flex items-center gap-2">
              <Tv size={18} className="text-indigo-400" />
              Active Subscriptions & Recurring Commitments Audit
            </h3>
            <p className="text-xs text-slate-400 mt-0.5">
              Identifies auto-renewing digital services and fixed recurring subscriptions.
            </p>
          </div>
          <button
            onClick={() => setActiveView('bills')}
            className="btn btn-secondary text-xs self-start sm:self-auto"
          >
            Manage Recurring Bills
          </button>
        </div>

        {subs.length === 0 ? (
          <p className="text-sm text-slate-400 py-4 text-center">No recurring subscriptions configured.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {subs.map(sub => (
              <div key={sub.id} className="p-3.5 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-center justify-between">
                <div>
                  <h4 className="font-semibold text-white text-sm">{sub.name}</h4>
                  <p className="text-xs text-slate-400 capitalize">{sub.recurrence} • Due day {sub.due_day}</p>
                </div>
                <div className="text-right">
                  <div className="text-sm font-bold text-white">
                    {formatCurrency(sub.amount, currencySymbol)}
                  </div>
                  <span className="text-[11px] text-slate-400">
                    {formatCurrency(Number(sub.amount) * 12, currencySymbol)}/yr
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
