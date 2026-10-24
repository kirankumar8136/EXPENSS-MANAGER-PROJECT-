# SmartSpend — Smart Expense Tracker & Personal Money Management System

[![React Version](https://img.shields.io/badge/React-18.3-blue.svg)](https://react.dev/)
[![Vite Version](https://img.shields.io/badge/Vite-6.0-purple.svg)](https://vitejs.dev/)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.4-orange.svg)](https://www.chartjs.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Interface](https://img.shields.io/badge/UI-Dark%20Financial%20SaaS-indigo.svg)](#)

> **A modern, intelligent personal finance web application built in React to help users make smarter financial decisions, plan salary capacity, monitor recurring commitments, and achieve their savings goals.**

---

## 🌟 Executive Overview

Most traditional expense trackers merely show users what they have already spent. **SmartSpend** is a forward-looking personal financial management platform built to answer critical financial questions:

1. **How much did I earn and where is every rupee going?**
2. **How much should I realistically save based on my salary?**
3. **What is my safe daily discretionary spending allowance?**
4. **Which bills are due soon, today, or overdue?**
5. **Will I have enough money for upcoming obligations?**
6. **"Can I Afford This?"** — Can I buy a ₹15,000 gadget today without harming upcoming bills or draining my emergency fund?

SmartSpend combines salary-based financial capacity planning, recurring bill scheduling, atomic bill payment recording, goal milestone tracking, deterministic behavioral insights, and an interactive affordability calculator into a clean, modern SaaS dashboard.

---

## 🚀 Live Local Execution (How to See the Output)

### Quick Start:
```bash
# 1. Open your terminal in the project directory
cd "c:\Users\kiran\Desktop\EXPENSS TRACKER PROJECT"

# 2. Start the Vite development server
npm run dev
```

### View in Browser:
Open your browser and navigate to:
```
http://localhost:5173/
```
*(The dev server is currently running and live!)*

---

## 🛠 Technology Stack

- **Frontend Framework**: [React 18.3](https://react.dev/) with hooks (`useContext`, `useMemo`, `useState`, `useEffect`).
- **Build Tool & Dev Server**: [Vite 6.0](https://vitejs.dev/) with lightning-fast HMR and optimized production bundling.
- **State Management & Persistence**: React Context API (`AppContext`) backed by persistent browser `localStorage`.
- **Financial Visualizations**: [Chart.js 4.4+](https://www.chartjs.org/) & [react-chartjs-2](https://react-chartjs-2.js.org/) (Income vs Expense bar charts, category breakdown doughnuts, 6-month trajectory lines, budget variance bars).
- **Icons & Typography**: [Lucide React](https://lucide.dev/) and Google Fonts [Inter](https://fonts.google.com/specimen/Inter).
- **Styling**: Custom modern dark financial SaaS design system (`src/index.css`) with CSS custom properties, responsive grid layouts, and mobile drawer navigation.

---

## 🎯 Key Features & Modules

### 1. 5-Step Financial Onboarding Wizard
- Collects income details, salary credit day, and opening cash reserves.
- Estimates non-negotiable living commitments (rent, food, electricity, transport, loans).
- Sets savings targets: **Conservative** (10%), **Balanced** (20%), or **Ambitious** (30%+).
- Transparently demonstrates the capacity equation before launching the dashboard.

### 2. Comprehensive Financial Dashboard
- **8 Core Financial Metric Cards**: Total Income, Total Expenses, Account Cash Balance, Planned Savings Target, Actual Savings Contributions, Upcoming Bills Total, Remaining Flexible Budget, and Budget Usage Percentage.
- **5 Dynamic Chart.js Visualizations**: Income vs. Expense bar chart, category doughnut, 6-month historical trajectory line, and budget utilization bars.
- **Upcoming Bills Widget**: Visual urgency badges (*Due Today, Due Soon, Overdue*) with 1-click modal payment recording.
- **Actionable Financial Insights**: Transparent recommendations alerting on discretionary burn rates and category spending surges.

### 3. Smart Salary-Based Capacity Planner
- **Transparent Mathematical Equation**:
  $$\text{Flexible Capacity} = \text{Monthly Income} - \text{Essential Commitments} - \text{Separate Unpaid Bills} - \text{Planned Savings}$$
- **Zero Double-Counting**: Automatically protects against subtracting bills twice if their category is already budgeted.
- **Safe Daily Discretionary Allowance**: Dynamically calculates how much you can spend per day across remaining days in the cycle.
- **Category Budgets**: Configurable monthly spending limits with color-coded utilization progress bars and 1-click recommended auto-allocation.

### 4. Recurring Bills & Payment Management
- Supports **weekly, monthly, quarterly, and yearly** frequencies.
- **Month-End Date Preservation**: Correctly handles bills due on the 31st across shorter months (February 28/29, April 30) without losing the original target day.
- **Atomic Payment Processing**: Recording a payment updates payment history, advances next due date, and automatically records a linked expense transaction.

### 5. Interactive Monthly Bill Calendar
- Full month grid displaying bills plotted on their exact due dates.
- Visual markers for paid vs. unpaid status with quick payment modal access.

### 6. "Can I Afford This?" Purchase Calculator
- Analyzes proposed purchases against:
  1. Current liquid bank balance.
  2. Upcoming unpaid bills due this month.
  3. Remaining flexible spending allowance.
  4. Active savings goals.
- Provides a clear evaluation verdict (*Affordable*, *Tight Budget*, or *Risky / Not Recommended*).
- Formulates 3 actionable alternatives: installment pacing, waiting for the next salary cycle, or cutting non-essential spending.

### 7. Smart Savings Goals Tracker
- Set financial milestones (e.g., Emergency Safety Fund, MacBook Pro, Vacation).
- Computes required monthly deposit pace: $\lceil(\text{Target} - \text{Saved}) / \text{Months Left}\rceil$.
- Supports deposit and withdrawal tracking with a complete audit trail.

### 8. Financial Insights & Subscription Auditor
- **Apples-to-Apples Period Comparison**: Compares Day 1–N of current month with Day 1–N of previous month to identify spending surges ($\ge 20\%$).
- **Subscription Auditor**: Automatically detects recurring digital entertainment and memberships, quantifying their total annual drain.

### 9. Filterable Ledger, Reports & CSV Export
- Search transactions by description, type, category, payment method, and date ranges.
- Direct in-browser CSV report generation and download.
- Print / Save as PDF capability formatted for financial statements.

### 10. Persistent Notification Center
- Real-time notification badge for overdue bills, upcoming payments, and budget surge warnings.
- Mark as read, mark all read, and quick links to resolve alerts.

---

## 📁 Project Architecture

```
smartspend/
├── index.html                   # HTML5 entry point with Inter font & dark theme
├── package.json                 # Project dependencies and scripts
├── vite.config.js               # Vite configuration with auto-port & host enabled
├── dist/                        # Production build output
└── src/
    ├── main.jsx                 # React root DOM renderer
    ├── App.jsx                  # Main application layout and view router
    ├── index.css                # Financial SaaS dark design system & CSS variables
    ├── context/
    │   └── AppContext.jsx       # Global application state, persistence & demo data
    ├── services/
    │   ├── budgetEngine.js      # Salary capacity formula & purchase evaluator
    │   ├── calculations.js      # Cash flow, balances, category breakdown & trajectory
    │   ├── recurrence.js        # Next due date, leap-year & month-end preservation
    │   └── insightsEngine.js    # Apples-to-apples surge detector & subscription auditor
    ├── components/
    │   ├── Sidebar.jsx          # Desktop navigation & mobile drawer
    │   ├── Header.jsx           # Top navbar, notification dropdown & quick actions
    │   ├── QuickTransactionModal.jsx  # Expense/Income entry modal
    │   ├── AffordabilityModal.jsx     # "Can I Afford This?" interactive modal
    │   └── PayBillModal.jsx           # Atomic bill payment confirmation modal
    └── views/
        ├── DashboardView.jsx    # 8 KPI cards, 4 Chart.js charts, recent widgets
        ├── TransactionsView.jsx # Ledger table, filters, pagination, CSV download
        ├── SmartBudgetView.jsx  # Capacity formula bar, daily allowance, category limits
        ├── BillsView.jsx        # Recurring bills overview, overdue cards, add bill modal
        ├── CalendarView.jsx     # Monthly calendar with bills plotted on due dates
        ├── SavingsGoalsView.jsx # Savings milestones, progress bars, deposit modal
        ├── AffordabilityView.jsx# Dedicated purchase affordability calculator view
        ├── InsightsView.jsx     # Behavioral analytics, surge comparison, subscription audit
        ├── ReportsView.jsx      # Financial statements, category breakdown, PDF/CSV export
        ├── CategoriesView.jsx   # Expense/Income category manager & custom creator
        ├── SettingsView.jsx     # Profile, currency selector (₹, $, €, £), demo reset
        ├── NotificationsView.jsx# Notification center with read/unread filter
        └── OnboardingView.jsx   # 5-step interactive onboarding wizard
```

---

## 💻 Available Scripts

- `npm run dev`: Starts the Vite development server at `http://localhost:5173/`.
- `npm run build`: Compiles optimized production bundle into `dist/`.
- `npm run preview`: Locally previews the production build.

---

## 📝 License

This project is licensed under the MIT License - open for educational, personal, and portfolio usage.
