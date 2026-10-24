import React, { createContext, useContext, useState, useEffect, useMemo } from 'react';
import { calculateNextDueDate } from '../services/recurrence';
import { getMonthlyCapacityPlan } from '../services/budgetEngine';

const AppContext = createContext();

export const DEFAULT_CATEGORIES = [
  // Expense Categories
  { id: 1, name: 'Housing & Rent', type: 'expense', icon: 'Home', color: '#EF4444', is_system: true, is_essential: true },
  { id: 2, name: 'Food & Groceries', type: 'expense', icon: 'ShoppingCart', color: '#F59E0B', is_system: true, is_essential: true },
  { id: 3, name: 'Electricity & Utilities', type: 'expense', icon: 'Zap', color: '#EAB308', is_system: true, is_essential: true },
  { id: 4, name: 'Transportation', type: 'expense', icon: 'Truck', color: '#3B82F6', is_system: true, is_essential: true },
  { id: 5, name: 'Healthcare & Medical', type: 'expense', icon: 'Activity', color: '#EC4899', is_system: true, is_essential: true },
  { id: 6, name: 'Education', type: 'expense', icon: 'BookOpen', color: '#8B5CF6', is_system: true, is_essential: true },
  { id: 7, name: 'Loan & EMI Payments', type: 'expense', icon: 'CreditCard', color: '#DC2626', is_system: true, is_essential: true },
  { id: 8, name: 'Insurance', type: 'expense', icon: 'Shield', color: '#06B6D4', is_system: true, is_essential: true },
  { id: 9, name: 'Shopping & Lifestyle', type: 'expense', icon: 'ShoppingBag', color: '#10B981', is_system: true, is_essential: false },
  { id: 10, name: 'Entertainment & Dining', type: 'expense', icon: 'Film', color: '#F97316', is_system: true, is_essential: false },
  { id: 11, name: 'Subscriptions & Media', type: 'expense', icon: 'Tv', color: '#6366F1', is_system: true, is_essential: false },
  { id: 12, name: 'Electronics & Gadgets', type: 'expense', icon: 'Cpu', color: '#14B8A6', is_system: true, is_essential: false },
  { id: 13, name: 'Travel & Vacations', type: 'expense', icon: 'Compass', color: '#0EA5E9', is_system: true, is_essential: false },
  { id: 14, name: 'Personal Care', type: 'expense', icon: 'Smile', color: '#A855F7', is_system: true, is_essential: false },
  { id: 15, name: 'Other Expense', type: 'expense', icon: 'MoreHorizontal', color: '#64748B', is_system: true, is_essential: false },

  // Income Categories
  { id: 16, name: 'Salary / Monthly Wage', type: 'income', icon: 'Briefcase', color: '#10B981', is_system: true, is_essential: false },
  { id: 17, name: 'Freelancing & Consulting', type: 'income', icon: 'Laptop', color: '#3B82F6', is_system: true, is_essential: false },
  { id: 18, name: 'Business Revenue', type: 'income', icon: 'TrendingUp', color: '#8B5CF6', is_system: true, is_essential: false },
  { id: 19, name: 'Investments & Dividends', type: 'income', icon: 'PieChart', color: '#06B6D4', is_system: true, is_essential: false },
  { id: 20, name: 'Gifts & Grants', type: 'income', icon: 'Gift', color: '#F59E0B', is_system: true, is_essential: false },
  { id: 21, name: 'Other Income', type: 'income', icon: 'PlusCircle', color: '#64748B', is_system: true, is_essential: false }
];

export function getInitialDemoData() {
  const today = new Date();
  const curYm = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
  const prevMonthDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
  const prevYm = `${prevMonthDate.getFullYear()}-${String(prevMonthDate.getMonth() + 1).padStart(2, '0')}`;

  const dueIn3Days = new Date(today);
  dueIn3Days.setDate(dueIn3Days.getDate() + 3);
  const dueIn6Days = new Date(today);
  dueIn6Days.setDate(dueIn6Days.getDate() + 6);
  const dueIn9Days = new Date(today);
  dueIn9Days.setDate(dueIn9Days.getDate() + 9);

  return {
    user: {
      name: 'Demo Professional',
      email: 'demo@smartspend.io',
      currency: '₹',
      currencyCode: 'INR',
      onboardingCompleted: true
    },
    settings: {
      monthly_income: 65000,
      income_source_type: 'salaried',
      income_frequency: 'monthly',
      salary_day: 1,
      is_income_fixed: true,
      monthly_savings_target: 15000,
      savings_style: 'balanced',
      reminder_days_before: '7,3,1,0'
    },
    openingBalance: 38500,
    categories: DEFAULT_CATEGORIES,
    budgets: [
      { id: 1, category_id: 1, amount: 18000 }, // Housing
      { id: 2, category_id: 2, amount: 12000 }, // Groceries
      { id: 3, category_id: 3, amount: 3500 },  // Electricity
      { id: 4, category_id: 4, amount: 4500 },  // Transportation
      { id: 5, category_id: 10, amount: 5500 }, // Dining
      { id: 6, category_id: 9, amount: 4000 },  // Shopping
      { id: 7, category_id: 11, amount: 1500 }  // Subscriptions
    ],
    recurringBills: [
      {
        id: 1,
        name: 'House Rent (Apartment)',
        category_id: 1,
        amount: 18000,
        frequency: 'monthly',
        due_day: 1,
        next_due_date: `${curYm}-28`,
        notes: 'Monthly flat rent transfer',
        status: 'active'
      },
      {
        id: 2,
        name: 'Electricity Bill (State Discom)',
        category_id: 3,
        amount: 2150,
        frequency: 'monthly',
        due_day: dueIn3Days.getDate(),
        next_due_date: dueIn3Days.toISOString().split('T')[0],
        notes: 'CA No. 100293848',
        status: 'active'
      },
      {
        id: 3,
        name: 'Broadband Fiber (Airtel Xstream)',
        category_id: 3,
        amount: 1179,
        frequency: 'monthly',
        due_day: dueIn6Days.getDate(),
        next_due_date: dueIn6Days.toISOString().split('T')[0],
        notes: '300 Mbps unlimited plan',
        status: 'active'
      },
      {
        id: 4,
        name: 'Netflix Premium 4K',
        category_id: 11,
        amount: 649,
        frequency: 'monthly',
        due_day: dueIn9Days.getDate(),
        next_due_date: dueIn9Days.toISOString().split('T')[0],
        notes: 'Monthly streaming',
        status: 'active'
      },
      {
        id: 5,
        name: 'Health Insurance Premium',
        category_id: 8,
        amount: 14500,
        frequency: 'yearly',
        due_day: 15,
        next_due_date: `${today.getFullYear()}-12-15`,
        notes: 'Annual floater medical insurance cover',
        status: 'active'
      }
    ],
    savingsGoals: [
      {
        id: 1,
        name: 'Emergency Safety Fund (6 Months)',
        target_amount: 180000,
        current_amount: 95000,
        target_date: `${today.getFullYear() + 1}-06-30`,
        priority: 'high',
        category: 'Emergency Fund',
        status: 'in_progress',
        contributions: [
          { id: 101, type: 'deposit', amount: 60000, date: `${prevYm}-15`, notes: 'Automated transfer' },
          { id: 102, type: 'deposit', amount: 35000, date: `${curYm}-05`, notes: 'Bonus allocation' }
        ]
      },
      {
        id: 2,
        name: 'Apple MacBook Pro M3',
        target_amount: 145000,
        current_amount: 65000,
        target_date: `${today.getFullYear() + 1}-03-31`,
        priority: 'medium',
        category: 'Technology & Gadgets',
        status: 'in_progress',
        contributions: [
          { id: 201, type: 'deposit', amount: 65000, date: `${curYm}-02`, notes: 'Initial savings allocation' }
        ]
      },
      {
        id: 3,
        name: 'Goa Year-End Vacation',
        target_amount: 35000,
        current_amount: 28000,
        target_date: `${today.getFullYear()}-12-28`,
        priority: 'medium',
        category: 'Travel & Vacation',
        status: 'in_progress',
        contributions: [
          { id: 301, type: 'deposit', amount: 28000, date: `${curYm}-10`, notes: 'Vacation savings' }
        ]
      }
    ],
    transactions: [
      // Salaries
      { id: 1, type: 'income', category_id: 16, amount: 65000, description: 'Monthly Salary - Tech Solutions Pvt Ltd', date: `${curYm}-01`, payment_method: 'bank_transfer', notes: 'Net take-home pay' },
      { id: 2, type: 'income', category_id: 16, amount: 65000, description: 'Monthly Salary - Tech Solutions Pvt Ltd', date: `${prevYm}-01`, payment_method: 'bank_transfer', notes: 'Net take-home pay' },
      { id: 3, type: 'income', category_id: 17, amount: 18500, description: 'UI/UX Consulting Milestone', date: `${curYm}-12`, payment_method: 'upi', notes: 'Freelance gig' },

      // Current Month Expenses
      { id: 4, type: 'expense', category_id: 1, amount: 18000, description: 'House Rent Payment Transfer', date: `${curYm}-02`, payment_method: 'bank_transfer' },
      { id: 5, type: 'expense', category_id: 2, amount: 3450, description: "Nature's Basket - Monthly Groceries", date: `${curYm}-04`, payment_method: 'credit_card' },
      { id: 6, type: 'expense', category_id: 2, amount: 890, description: 'Zepto Instant Delivery', date: `${curYm}-08`, payment_method: 'upi' },
      { id: 7, type: 'expense', category_id: 2, amount: 1420, description: 'Blinkit Essentials Refill', date: `${curYm}-14`, payment_method: 'upi' },
      { id: 8, type: 'expense', category_id: 10, amount: 1650, description: 'Dinner with Colleagues at Bistro', date: `${curYm}-05`, payment_method: 'credit_card' },
      { id: 9, type: 'expense', category_id: 10, amount: 480, description: 'Starbucks Coffee & Snacks', date: `${curYm}-09`, payment_method: 'upi' },
      { id: 10, type: 'expense', category_id: 10, amount: 980, description: 'PVR Cinemas IMAX Tickets', date: `${curYm}-15`, payment_method: 'upi' },
      { id: 11, type: 'expense', category_id: 4, amount: 2000, description: 'Petrol Refill - Indian Oil Pump', date: `${curYm}-03`, payment_method: 'credit_card' },
      { id: 12, type: 'expense', category_id: 4, amount: 480, description: 'Uber Ride to Client Office', date: `${curYm}-11`, payment_method: 'upi' },
      { id: 13, type: 'expense', category_id: 9, amount: 2499, description: 'Uniqlo Casual Shirt & Chinos', date: `${curYm}-07`, payment_method: 'credit_card' },
      { id: 14, type: 'expense', category_id: 11, amount: 649, description: 'Netflix Subscription Debit', date: `${curYm}-08`, payment_method: 'credit_card' },
      { id: 15, type: 'expense', category_id: 5, amount: 680, description: 'Apollo Pharmacy Health Supplements', date: `${curYm}-13`, payment_method: 'upi' },

      // Previous Month Expenses
      { id: 16, type: 'expense', category_id: 1, amount: 18000, description: 'House Rent Payment Transfer', date: `${prevYm}-02`, payment_method: 'bank_transfer' },
      { id: 17, type: 'expense', category_id: 2, amount: 4100, description: 'Supermarket Grocery Haul', date: `${prevYm}-05`, payment_method: 'credit_card' },
      { id: 18, type: 'expense', category_id: 10, amount: 2200, description: 'Weekend Dinner & Drinks', date: `${prevYm}-10`, payment_method: 'credit_card' },
      { id: 19, type: 'expense', category_id: 4, amount: 2000, description: 'Fuel Tank Refill', date: `${prevYm}-04`, payment_method: 'credit_card' },
      { id: 20, type: 'expense', category_id: 3, amount: 2150, description: 'Electricity Bill Paid', date: `${prevYm}-08`, payment_method: 'upi' }
    ],
    notifications: [
      {
        id: 1,
        title: 'Upcoming Bill: Electricity Bill',
        message: 'Your electricity payment of ₹2,150.00 is due in 3 days. Mark as paid when complete.',
        type: 'bill_due',
        is_read: false,
        created_at: new Date().toISOString()
      },
      {
        id: 2,
        title: 'Budget Alert: Entertainment & Dining',
        message: 'You have utilized 56% of your monthly Entertainment budget. ₹2,390 remaining.',
        type: 'budget_warning',
        is_read: false,
        created_at: new Date().toISOString()
      }
    ],
    billPayments: []
  };
}

export function AppProvider({ children }) {
  const [data, setData] = useState(() => {
    const saved = localStorage.getItem('smartspend_data');
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch (e) {
        console.error('Failed to parse saved state, using demo data', e);
      }
    }
    return getInitialDemoData();
  });

  const [activeView, setActiveView] = useState('dashboard');
  const [isQuickTxnOpen, setIsQuickTxnOpen] = useState(false);
  const [isAffordModalOpen, setIsAffordModalOpen] = useState(false);
  const [payBillData, setPayBillData] = useState(null); // { billId, name, amount, dueDate }

  // Sync to localStorage
  useEffect(() => {
    localStorage.setItem('smartspend_data', JSON.stringify(data));
  }, [data]);

  // Transaction Actions
  const addTransaction = (txn) => {
    setData(prev => ({
      ...prev,
      transactions: [
        {
          id: Date.now(),
          ...txn,
          amount: Number(txn.amount)
        },
        ...prev.transactions
      ]
    }));
  };

  const editTransaction = (id, updated) => {
    setData(prev => ({
      ...prev,
      transactions: prev.transactions.map(t => (t.id === id ? { ...t, ...updated, amount: Number(updated.amount) } : t))
    }));
  };

  const deleteTransaction = (id) => {
    setData(prev => ({
      ...prev,
      transactions: prev.transactions.filter(t => t.id !== id)
    }));
  };

  // Recurring Bill Actions
  const addBill = (bill) => {
    setData(prev => ({
      ...prev,
      recurringBills: [
        ...prev.recurringBills,
        {
          id: Date.now(),
          ...bill,
          amount: Number(bill.amount),
          status: 'active'
        }
      ]
    }));
  };

  const deleteBill = (id) => {
    setData(prev => ({
      ...prev,
      recurringBills: prev.recurringBills.filter(b => b.id !== id)
    }));
  };

  // Atomic Bill Payment:
  // 1. Records payment in billPayments
  // 2. Advances bill next_due_date
  // 3. Automatically adds linked expense transaction!
  const recordBillPayment = ({ billId, amountPaid, paidDate, paymentMethod = 'upi', paymentRef = '', notes = '' }) => {
    setData(prev => {
      const bill = prev.recurringBills.find(b => b.id === billId);
      if (!bill) return prev;

      const pAmount = Number(amountPaid);
      const nextDue = calculateNextDueDate(bill.next_due_date, bill.frequency, bill.due_day);

      const paymentRecord = {
        id: Date.now(),
        bill_id: billId,
        bill_name: bill.name,
        category_id: bill.category_id,
        amount_paid: pAmount,
        paid_date: paidDate,
        payment_method: paymentMethod,
        payment_reference: paymentRef,
        notes: notes
      };

      const linkedTxn = {
        id: Date.now() + 1,
        type: 'expense',
        category_id: bill.category_id,
        amount: pAmount,
        description: `Bill Payment: ${bill.name}`,
        date: paidDate,
        payment_method: paymentMethod,
        notes: notes ? `${notes} (Ref: ${paymentRef})` : (paymentRef ? `Ref: ${paymentRef}` : '')
      };

      const updatedBills = prev.recurringBills.map(b => (b.id === billId ? { ...b, next_due_date: nextDue } : b));

      return {
        ...prev,
        billPayments: [paymentRecord, ...(prev.billPayments || [])],
        recurringBills: updatedBills,
        transactions: [linkedTxn, ...prev.transactions]
      };
    });
  };

  // Savings Goal Actions
  const addSavingsGoal = (goal) => {
    setData(prev => ({
      ...prev,
      savingsGoals: [
        ...prev.savingsGoals,
        {
          id: Date.now(),
          ...goal,
          target_amount: Number(goal.target_amount),
          current_amount: Number(goal.current_amount || 0),
          status: 'in_progress',
          contributions: Number(goal.current_amount) > 0 ? [{
            id: Date.now() + 1,
            type: 'deposit',
            amount: Number(goal.current_amount),
            date: new Date().toISOString().split('T')[0],
            notes: 'Initial opening deposit'
          }] : []
        }
      ]
    }));
  };

  const deleteSavingsGoal = (id) => {
    setData(prev => ({
      ...prev,
      savingsGoals: prev.savingsGoals.filter(g => g.id !== id)
    }));
  };

  const contributeToGoal = (goalId, { type = 'deposit', amount, date, notes = '' }) => {
    const amt = Number(amount);
    setData(prev => ({
      ...prev,
      savingsGoals: prev.savingsGoals.map(g => {
        if (g.id !== goalId) return g;
        const newCurrent = type === 'deposit' ? g.current_amount + amt : Math.max(0, g.current_amount - amt);
        const newStatus = newCurrent >= g.target_amount ? 'completed' : 'in_progress';
        const newEntry = {
          id: Date.now(),
          type,
          amount: amt,
          date: date || new Date().toISOString().split('T')[0],
          notes
        };
        return {
          ...g,
          current_amount: newCurrent,
          status: newStatus,
          contributions: [newEntry, ...(g.contributions || [])]
        };
      })
    }));
  };

  // Budget Actions
  const setCategoryBudget = (categoryId, amount) => {
    const amt = Number(amount);
    setData(prev => {
      const exists = prev.budgets.some(b => b.category_id === categoryId);
      let updated;
      if (exists) {
        updated = prev.budgets.map(b => (b.category_id === categoryId ? { ...b, amount: amt } : b));
      } else {
        updated = [...prev.budgets, { id: Date.now(), category_id: categoryId, amount: amt }];
      }
      return { ...prev, budgets: updated };
    });
  };

  const applyRecommendedBudgets = (income) => {
    const effectiveIncome = Number(income) || data.settings.monthly_income || 45000;
    const ratios = {
      'Housing & Rent': 0.28,
      'Food & Groceries': 0.18,
      'Electricity & Utilities': 0.06,
      'Transportation': 0.08,
      'Healthcare & Medical': 0.05,
      'Entertainment & Dining': 0.08,
      'Shopping & Lifestyle': 0.07,
      'Subscriptions & Media': 0.03,
      'Personal Care': 0.04,
      'Other Expense': 0.03
    };

    const newBudgets = data.categories
      .filter(c => c.type === 'expense')
      .map(c => ({
        id: Date.now() + c.id,
        category_id: c.id,
        amount: Math.round((effectiveIncome * (ratios[c.name] || 0.03)) / 100) * 100
      }));

    setData(prev => ({
      ...prev,
      budgets: newBudgets
    }));
  };

  // Category Actions
  const addCategory = (cat) => {
    setData(prev => ({
      ...prev,
      categories: [
        ...prev.categories,
        {
          id: Date.now(),
          ...cat,
          is_system: false
        }
      ]
    }));
  };

  const deleteCategory = (id) => {
    setData(prev => ({
      ...prev,
      categories: prev.categories.filter(c => c.id !== id || c.is_system)
    }));
  };

  // Settings & Profile
  const updateSettings = (newSettings, newUser) => {
    setData(prev => ({
      ...prev,
      settings: { ...prev.settings, ...newSettings },
      user: { ...prev.user, ...newUser }
    }));
  };

  // Notifications
  const markNotificationRead = (id) => {
    setData(prev => ({
      ...prev,
      notifications: prev.notifications.map(n => (n.id === id ? { ...n, is_read: true } : n))
    }));
  };

  const markAllNotificationsRead = () => {
    setData(prev => ({
      ...prev,
      notifications: prev.notifications.map(n => ({ ...n, is_read: true }))
    }));
  };

  const resetToDemoData = () => {
    const demo = getInitialDemoData();
    setData(demo);
    localStorage.setItem('smartspend_data', JSON.stringify(demo));
  };

  // Derive currency symbol
  const currencySymbol = useMemo(() => {
    const c = data.settings?.currency || data.user?.currency || 'INR';
    if (c === 'USD') return '$';
    if (c === 'EUR') return '€';
    if (c === 'GBP') return '£';
    if (c === 'AED') return 'AED ';
    return '₹';
  }, [data.settings?.currency, data.user?.currency]);

  // Derive current month transactions
  const currentMonthTransactions = useMemo(() => {
    const now = new Date();
    const ym = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    return data.transactions.filter(t => t.date && t.date.startsWith(ym));
  }, [data.transactions]);

  // Compute live salary capacity plan
  const capacityPlan = useMemo(() => {
    return getMonthlyCapacityPlan({
      monthlyIncome: data.settings?.monthly_income || 65000,
      transactionsThisMonth: currentMonthTransactions,
      recurringBills: data.recurringBills || [],
      categoryBudgets: data.budgets || [],
      categories: data.categories || [],
      monthlySavingsTarget: data.settings?.planned_savings || 15000
    });
  }, [data.settings, currentMonthTransactions, data.recurringBills, data.budgets, data.categories]);

  return (
    <AppContext.Provider
      value={{
        ...data,
        bills: data.recurringBills,
        recurringBills: data.recurringBills,
        openingBalance: data.settings?.opening_balance ?? 42000,
        currencySymbol,
        capacityPlan,
        activeView,
        setActiveView,
        addTransaction,
        editTransaction,
        deleteTransaction,
        addBill,
        deleteBill,
        recordBillPayment,
        addSavingsGoal,
        deleteSavingsGoal,
        contributeToGoal,
        setCategoryBudget,
        applyRecommendedBudgets,
        addCategory,
        deleteCategory,
        updateSettings,
        markNotificationRead,
        markAllNotificationsRead,
        resetToDemoData,
        // Modal states
        isQuickTxnOpen,
        setIsQuickTxnOpen,
        isAffordModalOpen,
        setIsAffordModalOpen,
        payBillData,
        setPayBillData
      }}
    >
      {children}
    </AppContext.Provider>
  );
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
}
