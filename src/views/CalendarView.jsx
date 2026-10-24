import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../services/calculations';
import { ChevronLeft, ChevronRight, List } from 'lucide-react';

export default function CalendarView() {
  const { recurringBills, billPayments, user, setActiveView, setPayBillData } = useApp();

  const [currentDate, setCurrentDate] = useState(new Date());
  const currency = user?.currency || '₹';

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();

  const firstDay = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const startDayOfWeek = firstDay.getDay(); // 0 (Sun) to 6 (Sat)
  const monthLabel = firstDay.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });

  // Map bills to days
  const billsByDay = {};
  let totalDue = 0;
  let totalPaid = 0;

  const mStr = String(month + 1).padStart(2, '0');
  const monthPrefix = `${year}-${mStr}`;

  for (const b of recurringBills) {
    if (b.status === 'active' && b.next_due_date.startsWith(monthPrefix)) {
      const day = Number(b.next_due_date.split('-')[2]);
      if (!billsByDay[day]) billsByDay[day] = [];
      billsByDay[day].push({ ...b, isPaid: false });
      totalDue += Number(b.amount || 0);
    }
  }

  // Also map payments recorded in this month
  for (const p of billPayments) {
    if (p.paid_date?.startsWith(monthPrefix)) {
      const day = Number(p.paid_date.split('-')[2]);
      if (!billsByDay[day]) billsByDay[day] = [];
      billsByDay[day].push({
        id: p.id,
        name: p.bill_name,
        amount: p.amount_paid,
        isPaid: true
      });
      totalPaid += Number(p.amount_paid || 0);
    }
  }

  const prevMonth = () => {
    setCurrentDate(new Date(year, month - 1, 1));
  };

  const nextMonth = () => {
    setCurrentDate(new Date(year, month + 1, 1));
  };

  return (
    <div>
      {/* Calendar Navigation Bar */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <button className="btn btn-outline btn-sm" onClick={prevMonth}>
              <ChevronLeft size={16} /> Prev
            </button>
            <h2 style={{ fontSize: '1.25rem', margin: 0, minWidth: 160, textAlign: 'center' }}>
              {monthLabel}
            </h2>
            <button className="btn btn-outline btn-sm" onClick={nextMonth}>
              Next <ChevronRight size={16} />
            </button>
            <button className="btn btn-outline btn-sm" onClick={() => setCurrentDate(new Date())}>
              Today
            </button>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 20 }}>
            <div>
              <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Unpaid Due: </span>
              <strong style={{ color: 'var(--warning)', marginLeft: 4 }}>{formatCurrency(totalDue, currency)}</strong>
            </div>
            <div>
              <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Paid: </span>
              <strong style={{ color: 'var(--success)', marginLeft: 4 }}>{formatCurrency(totalPaid, currency)}</strong>
            </div>
            <button className="btn btn-primary btn-sm" onClick={() => setActiveView('bills')}>
              <List size={14} /> List View
            </button>
          </div>
        </div>
      </div>

      {/* Calendar Grid */}
      <div className="card">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 8 }}>
          {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
            <div
              key={d}
              style={{
                textAlign: 'center',
                padding: 10,
                fontSize: '0.78rem',
                fontWeight: 700,
                color: 'var(--text-muted)',
                textTransform: 'uppercase'
              }}
            >
              {d}
            </div>
          ))}

          {/* Empty cells before start of month */}
          {Array.from({ length: startDayOfWeek }).map((_, i) => (
            <div key={`empty-${i}`} style={{ minHeight: 110 }} />
          ))}

          {/* Day cells */}
          {Array.from({ length: daysInMonth }).map((_, i) => {
            const dayNum = i + 1;
            const isToday =
              dayNum === new Date().getDate() &&
              month === new Date().getMonth() &&
              year === new Date().getFullYear();
            const dayBills = billsByDay[dayNum] || [];

            return (
              <div
                key={`day-${dayNum}`}
                style={{
                  background: 'var(--bg-card)',
                  border: `1px solid ${isToday ? 'var(--primary)' : 'var(--border-color)'}`,
                  borderRadius: 'var(--radius-md)',
                  minHeight: 110,
                  padding: 8,
                  display: 'flex',
                  flexDirection: 'column'
                }}
              >
                <div
                  style={{
                    fontWeight: 700,
                    fontSize: '0.82rem',
                    color: isToday ? '#fff' : 'var(--text-muted)',
                    background: isToday ? 'var(--primary)' : 'transparent',
                    width: 22,
                    height: 22,
                    borderRadius: 'var(--radius-pill)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 6
                  }}
                >
                  {dayNum}
                </div>

                <div style={{ flex: 1, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 4 }}>
                  {dayBills.map((b, idx) => (
                    <div
                      key={idx}
                      onClick={() => {
                        if (!b.isPaid) {
                          setPayBillData({
                            billId: b.id,
                            name: b.name,
                            amount: b.amount,
                            dueDate: `${monthPrefix}-${String(dayNum).padStart(2, '0')}`
                          });
                        }
                      }}
                      style={{
                        fontSize: '0.72rem',
                        padding: '3px 6px',
                        borderRadius: 4,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        cursor: b.isPaid ? 'default' : 'pointer',
                        background: b.isPaid ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                        color: b.isPaid ? '#34d399' : '#fbbf24',
                        border: `1px solid ${b.isPaid ? 'rgba(16, 185, 129, 0.3)' : 'rgba(245, 158, 11, 0.3)'}`
                      }}
                    >
                      <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: 65, fontWeight: 600 }}>
                        {b.name}
                      </span>
                      <span style={{ fontWeight: 700 }}>
                        {currency}{Math.round(b.amount)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
