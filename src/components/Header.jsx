import React, { useState, useRef, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import {
  Menu,
  Bell,
  HelpCircle,
  Plus,
  AlertTriangle,
  Calendar,
  CheckCircle2,
  Clock
} from 'lucide-react';

const VIEW_TITLES = {
  dashboard: 'Financial Dashboard',
  transactions: 'Transactions Ledger',
  smart_budget: 'Smart Budget Engine',
  bills: 'Bills & Payments Manager',
  calendar: 'Monthly Bill Calendar',
  savings: 'Savings Goals Tracker',
  insights: 'Financial Insights',
  reports: 'Reports & Analytics',
  categories: 'Category Management',
  settings: 'User Settings',
  notifications: 'Notification Center',
  onboarding: 'Financial Onboarding Setup'
};

export default function Header({ onToggleSidebar }) {
  const {
    activeView,
    setActiveView,
    notifications,
    markNotificationRead,
    markAllNotificationsRead,
    setIsQuickTxnOpen,
    setIsAffordModalOpen,
    user
  } = useApp();

  const [isNotifOpen, setIsNotifOpen] = useState(false);
  const notifRef = useRef(null);

  const unreadCount = notifications.filter((n) => !n.is_read).length;

  useEffect(() => {
    function handleClickOutside(e) {
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setIsNotifOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const pageTitle = VIEW_TITLES[activeView] || 'SmartSpend';

  return (
    <header className="app-header">
      <div className="header-left">
        <button className="mobile-nav-toggle" onClick={onToggleSidebar} aria-label="Toggle Navigation">
          <Menu size={20} />
        </button>
        <h1 className="page-title">{pageTitle}</h1>
      </div>

      <div className="header-right">
        {/* Quick "Can I Afford This?" Shortcut */}
        <button
          className="btn btn-outline btn-sm"
          onClick={() => setIsAffordModalOpen(true)}
          title="Can I Afford This? Calculator"
        >
          <HelpCircle size={15} />
          <span>Can I Afford This?</span>
        </button>

        {/* Quick "Add Transaction" Shortcut */}
        <button
          className="btn btn-primary btn-sm"
          onClick={() => setIsQuickTxnOpen(true)}
          title="Record Expense or Income"
        >
          <Plus size={15} />
          <span>Add Transaction</span>
        </button>

        {/* Notification Bell Dropdown */}
        <div style={{ position: 'relative' }} ref={notifRef}>
          <button
            className="icon-btn"
            onClick={() => setIsNotifOpen(!isNotifOpen)}
            aria-label="Notifications"
          >
            <Bell size={18} />
            {unreadCount > 0 && <span className="badge-dot" />}
          </button>

          {isNotifOpen && (
            <div className="dropdown-menu" style={{ display: 'block' }}>
              <div className="dropdown-header">
                <h4 style={{ fontSize: '0.95rem', margin: 0 }}>Notifications</h4>
                {unreadCount > 0 ? (
                  <span className="badge badge-warning">{unreadCount} new</span>
                ) : (
                  <span className="badge badge-neutral">All caught up</span>
                )}
              </div>

              <div className="dropdown-body">
                {notifications.length === 0 ? (
                  <div style={{ padding: '24px 18px', textAlign: 'center', color: 'var(--text-muted)' }}>
                    No alerts right now.
                  </div>
                ) : (
                  notifications.slice(0, 5).map((n) => (
                    <div
                      key={n.id}
                      className={`notif-item ${!n.is_read ? 'unread' : ''}`}
                      onClick={() => {
                        markNotificationRead(n.id);
                        if (n.type.includes('bill')) setActiveView('bills');
                        else if (n.type.includes('budget')) setActiveView('smart_budget');
                        setIsNotifOpen(false);
                      }}
                    >
                      <div className={`notif-icon ${n.type}`}>
                        {n.type === 'bill_overdue' && <AlertTriangle size={16} />}
                        {n.type === 'bill_due' && <Calendar size={16} />}
                        {n.type === 'budget_warning' && <Clock size={16} />}
                        {n.type === 'system' && <Bell size={16} />}
                      </div>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: '0.84rem', fontWeight: 600, color: 'var(--text-primary)', marginBottom: 2 }}>
                          {n.title}
                        </div>
                        <div style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', lineHeight: 1.35 }}>
                          {n.message}
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>

              <div
                style={{
                  padding: '10px 18px',
                  textAlign: 'center',
                  backgroundColor: 'var(--bg-card)',
                  borderTop: '1px solid var(--border-color)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center'
                }}
              >
                <button
                  onClick={() => {
                    markAllNotificationsRead();
                  }}
                  style={{ background: 'none', border: 'none', color: 'var(--text-muted)', fontSize: '0.78rem', cursor: 'pointer' }}
                >
                  Mark all read
                </button>
                <button
                  onClick={() => {
                    setActiveView('notifications');
                    setIsNotifOpen(false);
                  }}
                  style={{ background: 'none', border: 'none', color: 'var(--primary)', fontSize: '0.82rem', fontWeight: 600, cursor: 'pointer' }}
                >
                  View All &rarr;
                </button>
              </div>
            </div>
          )}
        </div>

        {/* User Avatar */}
        <button
          className="user-avatar"
          onClick={() => setActiveView('settings')}
          title="Account Settings"
          style={{ border: 'none', cursor: 'pointer' }}
        >
          {user?.name ? user.name[0].toUpperCase() : 'U'}
        </button>
      </div>
    </header>
  );
}
