import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Bell, 
  CheckCheck, 
  AlertTriangle, 
  Info, 
  Calendar, 
  ShieldAlert, 
  ArrowRight,
  Clock
} from 'lucide-react';

export default function NotificationsView() {
  const { notifications, markNotificationRead, markAllNotificationsRead, setActiveView } = useApp();
  const [filter, setFilter] = useState('all');

  const filtered = notifications.filter(n => {
    if (filter === 'unread') return !n.is_read;
    return true;
  });

  const unreadCount = notifications.filter(n => !n.is_read).length;

  const handleAction = (item) => {
    markNotificationRead(item.id);
    if (item.action_view) {
      setActiveView(item.action_view);
    }
  };

  const renderIcon = (type) => {
    switch (type) {
      case 'danger':
        return <AlertTriangle size={18} className="text-rose-400" />;
      case 'warning':
        return <ShieldAlert size={18} className="text-amber-400" />;
      default:
        return <Info size={18} className="text-cyan-400" />;
    }
  };

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-white flex items-center gap-2">
            <Bell className="text-indigo-400" size={26} />
            Notifications & System Alerts
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            Real-time notifications for bill payment deadlines, surge alerts, and budget milestones.
          </p>
        </div>
        {unreadCount > 0 && (
          <button
            onClick={markAllNotificationsRead}
            className="btn btn-secondary text-xs flex items-center gap-1.5 self-start sm:self-auto"
          >
            <CheckCheck size={14} />
            <span>Mark All as Read ({unreadCount})</span>
          </button>
        )}
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-3 border-b border-slate-800 pb-3">
        <button
          onClick={() => setFilter('all')}
          className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
            filter === 'all'
              ? 'bg-slate-700 text-white'
              : 'text-slate-400 hover:text-white hover:bg-slate-800'
          }`}
        >
          All Notifications ({notifications.length})
        </button>
        <button
          onClick={() => setFilter('unread')}
          className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
            filter === 'unread'
              ? 'bg-indigo-600/30 text-indigo-300 border border-indigo-500/30'
              : 'text-slate-400 hover:text-white hover:bg-slate-800'
          }`}
        >
          Unread ({unreadCount})
        </button>
      </div>

      {/* Notifications List */}
      <div className="space-y-3">
        {filtered.length === 0 ? (
          <div className="card p-8 text-center text-slate-400">
            <Bell size={40} className="mx-auto text-slate-600 mb-2 opacity-50" />
            <p className="text-white font-medium">No notifications found</p>
            <p className="text-xs mt-1">
              {filter === 'unread' ? 'You have read all your alerts!' : 'No system notifications recorded.'}
            </p>
          </div>
        ) : (
          filtered.map(item => (
            <div
              key={item.id}
              className={`card p-4 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                !item.is_read ? 'border-l-4 border-l-indigo-500 bg-slate-850' : 'opacity-85'
              }`}
            >
              <div className="flex items-start gap-3.5">
                <div className="p-2.5 rounded-lg bg-slate-800 border border-slate-700/60 mt-0.5">
                  {renderIcon(item.type)}
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h4 className="font-semibold text-white text-sm">{item.title}</h4>
                    {!item.is_read && (
                      <span className="w-2 h-2 rounded-full bg-indigo-500 inline-block" />
                    )}
                  </div>
                  <p className="text-xs text-slate-300 mt-1 leading-relaxed">
                    {item.message}
                  </p>
                  <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-2">
                    <Clock size={12} />
                    <span>{item.created_at || 'Recently'}</span>
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                {!item.is_read && (
                  <button
                    onClick={() => markNotificationRead(item.id)}
                    className="btn btn-secondary text-xs px-2.5 py-1"
                    title="Mark Read"
                  >
                    Mark Read
                  </button>
                )}
                {item.action_view && (
                  <button
                    onClick={() => handleAction(item)}
                    className="btn btn-primary text-xs px-3 py-1 flex items-center gap-1"
                  >
                    <span>View</span>
                    <ArrowRight size={12} />
                  </button>
                )}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
