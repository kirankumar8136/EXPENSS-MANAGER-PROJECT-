import React from 'react';
import { useApp } from '../context/AppContext';
import {
  LayoutDashboard,
  ArrowLeftRight,
  PieChart,
  CalendarClock,
  Calendar,
  Target,
  Zap,
  BarChart3,
  Tags,
  Settings,
  WalletCards,
  RotateCcw
} from 'lucide-react';

export default function Sidebar({ isOpen, onClose }) {
  const { activeView, setActiveView, user, resetToDemoData } = useApp();

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'transactions', label: 'Transactions', icon: ArrowLeftRight },
    { id: 'smart_budget', label: 'Smart Budget', icon: PieChart },
    { id: 'bills', label: 'Bills & Payments', icon: CalendarClock },
    { id: 'calendar', label: 'Calendar', icon: Calendar },
    { id: 'savings', label: 'Savings Goals', icon: Target },
    { id: 'insights', label: 'Financial Insights', icon: Zap },
    { id: 'reports', label: 'Reports', icon: BarChart3 },
    { id: 'categories', label: 'Categories', icon: Tags },
    { id: 'settings', label: 'Settings', icon: Settings }
  ];

  const handleNavClick = (id) => {
    setActiveView(id);
    if (onClose) onClose();
  };

  return (
    <aside className={`app-sidebar ${isOpen ? 'show' : ''}`} id="appSidebar">
      {/* Brand / Logo */}
      <div className="sidebar-header">
        <div className="brand-logo" onClick={() => handleNavClick('dashboard')}>
          <div className="brand-icon">
            <WalletCards size={20} />
          </div>
          <span>SmartSpend</span>
        </div>
      </div>

      {/* Nav Menu */}
      <nav className="sidebar-nav">
        <div className="nav-label">Core Overview</div>
        {navItems.slice(0, 2).map((item) => {
          const Icon = item.icon;
          const isActive = activeView === item.id;
          return (
            <button
              key={item.id}
              className={`nav-item ${isActive ? 'active' : ''}`}
              onClick={() => handleNavClick(item.id)}
            >
              <Icon size={18} />
              <span>{item.label}</span>
            </button>
          );
        })}

        <div className="nav-label">Financial Planning</div>
        {navItems.slice(2, 6).map((item) => {
          const Icon = item.icon;
          const isActive = activeView === item.id;
          return (
            <button
              key={item.id}
              className={`nav-item ${isActive ? 'active' : ''}`}
              onClick={() => handleNavClick(item.id)}
            >
              <Icon size={18} />
              <span>{item.label}</span>
            </button>
          );
        })}

        <div className="nav-label">Intelligence & Analytics</div>
        {navItems.slice(6, 8).map((item) => {
          const Icon = item.icon;
          const isActive = activeView === item.id;
          return (
            <button
              key={item.id}
              className={`nav-item ${isActive ? 'active' : ''}`}
              onClick={() => handleNavClick(item.id)}
            >
              <Icon size={18} />
              <span>{item.label}</span>
            </button>
          );
        })}

        <div className="nav-label">Preferences</div>
        {navItems.slice(8).map((item) => {
          const Icon = item.icon;
          const isActive = activeView === item.id;
          return (
            <button
              key={item.id}
              className={`nav-item ${isActive ? 'active' : ''}`}
              onClick={() => handleNavClick(item.id)}
            >
              <Icon size={18} />
              <span>{item.label}</span>
            </button>
          );
        })}
      </nav>

      {/* Sidebar Footer */}
      <div className="sidebar-footer">
        <div className="user-mini-card">
          <div className="user-avatar">
            {user?.name ? user.name[0].toUpperCase() : 'U'}
          </div>
          <div className="user-meta">
            <div className="user-name">{user?.name || 'Demo User'}</div>
            <div className="user-sub">{user?.email || 'demo@smartspend.io'}</div>
          </div>
          <button
            onClick={() => {
              if (window.confirm('Reset all data to default demo state?')) {
                resetToDemoData();
              }
            }}
            title="Reset to Demo Data"
            style={{
              background: 'none',
              border: 'none',
              color: 'var(--text-muted)',
              cursor: 'pointer',
              padding: 4,
              display: 'flex',
              alignItems: 'center'
            }}
          >
            <RotateCcw size={16} />
          </button>
        </div>
      </div>
    </aside>
  );
}
