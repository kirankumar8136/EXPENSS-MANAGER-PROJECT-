import React, { useState, useMemo } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import { 
  FileText, 
  Download, 
  Printer, 
  Calendar, 
  PieChart, 
  CreditCard, 
  TrendingUp, 
  TrendingDown, 
  ShieldCheck,
  ArrowUpRight,
  ArrowDownRight
} from 'lucide-react';

export default function ReportsView() {
  const { transactions, categories, currencySymbol } = useApp();
  const [period, setPeriod] = useState('current_month');

  // Filter transactions based on selected period
  const filteredData = useMemo(() => {
    const now = new Date();
    const curYear = now.getFullYear();
    const curMonth = now.getMonth();

    let startDate, endDate;

    if (period === 'current_month') {
      startDate = new Date(curYear, curMonth, 1);
      endDate = new Date(curYear, curMonth + 1, 0);
    } else if (period === 'last_month') {
      startDate = new Date(curYear, curMonth - 1, 1);
      endDate = new Date(curYear, curMonth, 0);
    } else if (period === 'last_3_months') {
      startDate = new Date(curYear, curMonth - 2, 1);
      endDate = new Date(curYear, curMonth + 1, 0);
    } else if (period === 'this_year') {
      startDate = new Date(curYear, 0, 1);
      endDate = new Date(curYear, 11, 31);
    } else {
      // all time
      startDate = new Date(2000, 0, 1);
      endDate = new Date(2100, 11, 31);
    }

    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];

    const matched = transactions.filter(t => t.date >= startStr && t.date <= endStr);

    let totalIncome = 0;
    let totalExpense = 0;
    const catMap = {};
    const methodMap = {};

    matched.forEach(t => {
      const amt = Number(t.amount || 0);
      if (t.type === 'income') {
        totalIncome += amt;
      } else {
        totalExpense += amt;
        
        // Category grouping
        const cat = categories.find(c => c.id === t.category_id);
        const catName = cat ? cat.name : (t.category_name || 'Uncategorized');
        if (!catMap[catName]) {
          catMap[catName] = { 
            name: catName, 
            amount: 0, 
            count: 0, 
            isEssential: cat ? cat.is_essential : false,
            color: cat ? cat.color : '#6366f1' 
          };
        }
        catMap[catName].amount += amt;
        catMap[catName].count += 1;

        // Payment method grouping
        const method = t.payment_method || 'Other';
        if (!methodMap[method]) {
          methodMap[method] = { method, amount: 0, count: 0 };
        }
        methodMap[method].amount += amt;
        methodMap[method].count += 1;
      }
    });

    const netCashFlow = totalIncome - totalExpense;
    const savingsRate = totalIncome > 0 ? Math.max(0, Math.round((netCashFlow / totalIncome) * 100)) : 0;

    const categoryList = Object.values(catMap).map(c => ({
      ...c,
      pctOfTotal: totalExpense > 0 ? ((c.amount / totalExpense) * 100).toFixed(1) : 0,
      avgSize: c.count > 0 ? Math.round(c.amount / c.count) : 0
    })).sort((a, b) => b.amount - a.amount);

    const methodList = Object.values(methodMap).map(m => ({
      ...m,
      pctOfTotal: totalExpense > 0 ? ((m.amount / totalExpense) * 100).toFixed(1) : 0
    })).sort((a, b) => b.amount - a.amount);

    return {
      startDate: startStr,
      endDate: endStr,
      transactions: matched,
      totalIncome,
      totalExpense,
      netCashFlow,
      savingsRate,
      categoryList,
      methodList
    };
  }, [transactions, categories, period]);

  // Export Filtered CSV
  const handleExportCSV = () => {
    const headers = ['Transaction ID', 'Date', 'Type', 'Category', 'Description', 'Amount', 'Payment Method'];
    const rows = filteredData.transactions.map(t => {
      const cat = categories.find(c => c.id === t.category_id);
      return [
        t.id,
        t.date,
        t.type,
        `"${(cat?.name || t.category_name || '').replace(/"/g, '""')}"`,
        `"${(t.description || '').replace(/"/g, '""')}"`,
        t.amount,
        `"${(t.payment_method || '').replace(/"/g, '""')}"`
      ];
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `smartspend_report_${period}_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      {/* Header and Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-white flex items-center gap-2">
            <FileText className="text-indigo-400" size={26} />
            Financial Statement & Reports
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            Auditable cash flow statements, category distribution, and payment method summaries.
          </p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          <div className="relative">
            <select
              value={period}
              onChange={e => setPeriod(e.target.value)}
              className="input pr-8 py-2 text-xs font-medium cursor-pointer"
            >
              <option value="current_month">Current Month</option>
              <option value="last_month">Last Month</option>
              <option value="last_3_months">Last 3 Months</option>
              <option value="this_year">This Year</option>
              <option value="all_time">All Time</option>
            </select>
          </div>
          <button
            onClick={handleExportCSV}
            className="btn btn-secondary text-xs flex items-center gap-1.5"
            title="Export CSV"
          >
            <Download size={14} />
            <span>CSV Export</span>
          </button>
          <button
            onClick={() => window.print()}
            className="btn btn-secondary text-xs flex items-center gap-1.5"
            title="Print or Save PDF"
          >
            <Printer size={14} />
            <span>Print / PDF</span>
          </button>
        </div>
      </div>

      {/* Cash Flow Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 uppercase font-semibold">Total Revenue Inflows</span>
            <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
              <ArrowDownRight size={18} />
            </div>
          </div>
          <div className="text-2xl font-bold text-emerald-400 mt-2">
            {formatCurrency(filteredData.totalIncome, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            {filteredData.transactions.filter(t => t.type === 'income').length} income events recorded
          </p>
        </div>

        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 uppercase font-semibold">Total Expenditures</span>
            <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
              <ArrowUpRight size={18} />
            </div>
          </div>
          <div className="text-2xl font-bold text-rose-400 mt-2">
            {formatCurrency(filteredData.totalExpense, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            {filteredData.transactions.filter(t => t.type === 'expense').length} expense transactions
          </p>
        </div>

        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 uppercase font-semibold">Net Cash Flow</span>
            <div className={`p-2 rounded-lg ${filteredData.netCashFlow >= 0 ? 'bg-indigo-500/10 text-indigo-400' : 'bg-rose-500/10 text-rose-400'}`}>
              {filteredData.netCashFlow >= 0 ? <TrendingUp size={18} /> : <TrendingDown size={18} />}
            </div>
          </div>
          <div className={`text-2xl font-bold mt-2 ${filteredData.netCashFlow >= 0 ? 'text-white' : 'text-rose-400'}`}>
            {filteredData.netCashFlow >= 0 ? '+' : ''}{formatCurrency(filteredData.netCashFlow, currencySymbol)}
          </div>
          <p className="text-xs text-slate-400 mt-1">
            {filteredData.netCashFlow >= 0 ? 'Operating cash surplus' : 'Operating cash deficit'}
          </p>
        </div>

        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 uppercase font-semibold">Net Savings Rate</span>
            <div className="p-2 rounded-lg bg-cyan-500/10 text-cyan-400">
              <ShieldCheck size={18} />
            </div>
          </div>
          <div className="text-2xl font-bold text-cyan-400 mt-2">
            {filteredData.savingsRate}%
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Retained portion of total incoming revenue
          </p>
        </div>
      </div>

      {/* Category Breakdown Table */}
      <div className="card overflow-hidden">
        <div className="p-5 border-b border-slate-800">
          <h3 className="text-base font-semibold text-white flex items-center gap-2">
            <PieChart size={18} className="text-indigo-400" />
            Expenditure Distribution by Category
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">
            Normalized breakdown of outflow volume, category frequency, and average ticket size.
          </p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-800/60 text-xs font-semibold text-slate-400 uppercase tracking-wider">
              <tr>
                <th className="py-3 px-4">Category</th>
                <th className="py-3 px-4">Type</th>
                <th className="py-3 px-4 text-center">Transactions</th>
                <th className="py-3 px-4">Total Amount</th>
                <th className="py-3 px-4">Avg Ticket</th>
                <th className="py-3 px-4">% of Total Outflow</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-300">
              {filteredData.categoryList.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-500">
                    No expense transactions found for the selected period.
                  </td>
                </tr>
              ) : (
                filteredData.categoryList.map(item => (
                  <tr key={item.name} className="hover:bg-slate-800/40 transition-colors">
                    <td className="py-3.5 px-4 font-medium text-white flex items-center gap-2">
                      <span 
                        className="w-2.5 h-2.5 rounded-full inline-block" 
                        style={{ backgroundColor: item.color }}
                      />
                      {item.name}
                    </td>
                    <td className="py-3.5 px-4">
                      {item.isEssential ? (
                        <span className="badge badge-info text-[10px]">Essential Need</span>
                      ) : (
                        <span className="badge badge-secondary text-[10px]">Discretionary</span>
                      )}
                    </td>
                    <td className="py-3.5 px-4 text-center text-slate-400">
                      {item.count}
                    </td>
                    <td className="py-3.5 px-4 font-semibold text-white">
                      {formatCurrency(item.amount, currencySymbol)}
                    </td>
                    <td className="py-3.5 px-4 text-slate-400">
                      {formatCurrency(item.avgSize, currencySymbol)}
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-2">
                        <div className="w-24 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                          <div 
                            className="bg-indigo-500 h-1.5 rounded-full" 
                            style={{ width: `${Math.min(100, item.pctOfTotal)}%` }}
                          />
                        </div>
                        <span className="text-xs font-semibold text-slate-300">
                          {item.pctOfTotal}%
                        </span>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Payment Method Distribution */}
      <div className="card overflow-hidden">
        <div className="p-5 border-b border-slate-800">
          <h3 className="text-base font-semibold text-white flex items-center gap-2">
            <CreditCard size={18} className="text-cyan-400" />
            Payment Channel Breakdown
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">
            Spending volume categorized by transaction rail (UPI, Cards, Net Banking, Cash).
          </p>
        </div>
        <div className="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {filteredData.methodList.map(m => (
            <div key={m.method} className="p-4 rounded-lg bg-slate-800/60 border border-slate-700/60">
              <div className="flex items-center justify-between text-xs text-slate-400 mb-1">
                <span className="font-semibold text-white">{m.method}</span>
                <span>{m.count} payments</span>
              </div>
              <div className="text-lg font-bold text-white">
                {formatCurrency(m.amount, currencySymbol)}
              </div>
              <div className="flex items-center gap-2 mt-2">
                <div className="w-full bg-slate-750 rounded-full h-1 overflow-hidden">
                  <div 
                    className="bg-cyan-500 h-1 rounded-full" 
                    style={{ width: `${m.pctOfTotal}%` }}
                  />
                </div>
                <span className="text-[11px] text-slate-400 font-medium">{m.pctOfTotal}%</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
