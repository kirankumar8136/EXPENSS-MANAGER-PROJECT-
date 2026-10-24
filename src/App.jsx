import React, { useState } from 'react';
import { useApp } from './context/AppContext';

// Layout & Modals
import Sidebar from './components/Sidebar';
import Header from './components/Header';
import QuickTransactionModal from './components/QuickTransactionModal';
import AffordabilityModal from './components/AffordabilityModal';
import PayBillModal from './components/PayBillModal';

// Views
import DashboardView from './views/DashboardView';
import TransactionsView from './views/TransactionsView';
import SmartBudgetView from './views/SmartBudgetView';
import BillsView from './views/BillsView';
import CalendarView from './views/CalendarView';
import SavingsGoalsView from './views/SavingsGoalsView';
import InsightsView from './views/InsightsView';
import ReportsView from './views/ReportsView';
import CategoriesView from './views/CategoriesView';
import SettingsView from './views/SettingsView';
import NotificationsView from './views/NotificationsView';
import OnboardingView from './views/OnboardingView';
import AffordabilityView from './views/AffordabilityView';

export default function App() {
  const { activeView } = useApp();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const renderActiveView = () => {
    switch (activeView) {
      case 'dashboard':
        return <DashboardView />;
      case 'transactions':
        return <TransactionsView />;
      case 'smart_budget':
        return <SmartBudgetView />;
      case 'bills':
        return <BillsView />;
      case 'calendar':
        return <CalendarView />;
      case 'savings':
        return <SavingsGoalsView />;
      case 'insights':
        return <InsightsView />;
      case 'reports':
        return <ReportsView />;
      case 'categories':
        return <CategoriesView />;
      case 'settings':
        return <SettingsView />;
      case 'notifications':
        return <NotificationsView />;
      case 'onboarding':
        return <OnboardingView />;
      case 'affordability':
        return <AffordabilityView />;
      default:
        return <DashboardView />;
    }
  };

  return (
    <div className="app-layout">
      {/* Sidebar for Desktop & Mobile Drawer */}
      <Sidebar 
        isOpen={sidebarOpen} 
        onClose={() => setSidebarOpen(false)} 
      />

      {/* Mobile Backdrop Overlay */}
      {sidebarOpen && (
        <div 
          className="fixed inset-0 bg-black/60 z-30 lg:hidden backdrop-blur-xs transition-opacity"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Main Content Area */}
      <div className="app-main">
        <Header onToggleSidebar={() => setSidebarOpen(!sidebarOpen)} />
        
        <main className="app-content">
          {renderActiveView()}
        </main>
      </div>

      {/* Global Modals */}
      <QuickTransactionModal />
      <AffordabilityModal />
      <PayBillModal />
    </div>
  );
}
