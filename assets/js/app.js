/**
 * SmartSpend Global Application Script
 * Manages UI interactions, modals, notifications, Lucide icons,
 * and AJAX helpers.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 2. Mobile Sidebar Navigation Drawer
    const mobileToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('appSidebar');

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    // 3. Notification Center Dropdown
    const notifBtn = document.getElementById('notificationBtn');
    const notifMenu = document.getElementById('notificationMenu');
    const notifBadge = document.getElementById('notificationBadge');

    if (notifBtn && notifMenu) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!notifMenu.contains(e.target) && !notifBtn.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });
    }

    // 4. Modal Management System
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    };

    // Close modal on backdrop click or close button
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        const closeBtn = backdrop.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                backdrop.classList.remove('show');
                document.body.style.overflow = '';
            } );
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.show').forEach((m) => {
                m.classList.remove('show');
            });
            document.body.style.overflow = '';
        }
    });

    // 5. "Can I Afford This?" Interactive Calculator Handler
    const affordForm = document.getElementById('affordabilityCalculatorForm');
    const affordResults = document.getElementById('affordabilityResults');

    if (affordForm) {
        affordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = affordForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Analyzing Finances...</span>';

            const formData = new FormData(affordForm);

            try {
                const response = await fetch('actions/calculator_actions.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();

                if (data.success && data.result) {
                    renderAffordabilityResult(data.result);
                } else {
                    alert(data.error || 'Failed to calculate affordability.');
                }
            } catch (err) {
                console.error(err);
                alert('An unexpected error occurred during affordability evaluation.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        });
    }

    function renderAffordabilityResult(res) {
        if (!affordResults) return;

        let statusClass = 'success';
        let badgeText = 'Affordable';
        let badgeBg = 'var(--success-light)';
        let badgeColor = 'var(--success)';

        if (res.status === 'risky') {
            statusClass = 'danger';
            badgeText = 'Not Recommended';
            badgeBg = 'var(--danger-light)';
            badgeColor = 'var(--danger)';
        } else if (res.status === 'tight') {
            statusClass = 'warning';
            badgeText = 'Budget Tight';
            badgeBg = 'var(--warning-light)';
            badgeColor = 'var(--warning)';
        }

        let reasonsHtml = res.reasons.map(r => `<li>${r}</li>`).join('');
        let alternativesHtml = '';

        if (res.alternatives && res.alternatives.length > 0) {
            alternativesHtml = `
                <div style="margin-top: 16px;">
                    <h5 style="font-size: 0.86rem; color: var(--text-primary); margin-bottom: 8px;">Smart Financial Alternatives:</h5>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        ${res.alternatives.map(a => `
                            <div style="padding: 10px 12px; background: rgba(255,255,255,0.04); border-radius: 8px; border: 1px solid var(--border-color);">
                                <strong style="font-size: 0.82rem; color: var(--primary);">${a.title}</strong>
                                <p style="font-size: 0.78rem; color: var(--text-secondary); margin: 2px 0 0;">${a.description}</p>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        affordResults.innerHTML = `
            <div style="margin-top: 24px; padding: 20px; border-radius: 12px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Evaluation Result</span>
                    <span style="padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; background: ${badgeBg}; color: ${badgeColor};">${badgeText}</span>
                </div>
                <h4 style="font-size: 1rem; color: var(--text-primary); margin-bottom: 10px;">${res.verdict}</h4>
                <ul style="font-size: 0.82rem; color: var(--text-secondary); padding-left: 20px; display: flex; flex-direction: column; gap: 6px;">
                    ${reasonsHtml}
                </ul>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">Daily Discretionary Now</div>
                        <div style="font-weight: 700; color: var(--text-primary);">₹${Number(res.impacts.current_daily_allowance).toFixed(2)}/day</div>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">Daily Allowance After</div>
                        <div style="font-weight: 700; color: ${res.impacts.new_daily_allowance > 0 ? 'var(--primary)' : 'var(--danger)'};">₹${Number(res.impacts.new_daily_allowance).toFixed(2)}/day</div>
                    </div>
                </div>

                ${alternativesHtml}

                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 14px; font-style: italic;">
                    ${res.disclaimer}
                </div>
            </div>
        `;
        affordResults.style.display = 'block';
    }

    // 6. Record Bill Payment Modal Trigger Helper
    window.openPayBillModal = function (billId, billName, amount, dueDate) {
        const form = document.getElementById('recordBillPaymentForm');
        if (form) {
            form.querySelector('input[name="bill_id"]').value = billId;
            form.querySelector('input[name="amount_paid"]').value = amount;
            form.querySelector('input[name="paid_date"]').value = new Date().toISOString().split('T')[0];
            const nameEl = document.getElementById('payBillModalName');
            if (nameEl) nameEl.textContent = billName;
            const dueEl = document.getElementById('payBillModalDue');
            if (dueEl) dueEl.textContent = dueDate;
        }
        openModal('recordBillPaymentModal');
    };
});
