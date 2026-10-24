/**
 * SmartSpend Chart.js Data Visualizations
 * Provides responsive dark-themed charts with crisp formatting and animations.
 */

window.SmartSpendCharts = {
    incomeVsExpenseChart: null,
    categoryDoughnutChart: null,
    expenseTrendChart: null,
    budgetVsActualChart: null,

    // Common Chart.js Dark Theme defaults
    applyDefaults() {
        if (typeof Chart === 'undefined') return;

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';
        Chart.defaults.plugins.tooltip.titleColor = '#f8fafc';
        Chart.defaults.plugins.tooltip.bodyColor = '#cbd5e1';
        Chart.defaults.plugins.tooltip.borderColor = 'rgba(255, 255, 255, 0.1)';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
    },

    // 1. Income vs Expense Bar Chart
    initIncomeVsExpense(canvasId, incomeAmount, expenseAmount, currency = '₹') {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        this.applyDefaults();

        if (this.incomeVsExpenseChart) {
            this.incomeVsExpenseChart.destroy();
        }

        this.incomeVsExpenseChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Income', 'Total Expenses'],
                datasets: [{
                    data: [incomeAmount, expenseAmount],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderRadius: 8,
                    barPercentage: 0.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return ` ${currency}${Number(context.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            color: '#94a3b8',
                            callback(value) {
                                return currency + Number(value).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    },

    // 2. Category Doughnut Chart
    initCategoryDoughnut(canvasId, categories, currency = '₹') {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        this.applyDefaults();

        if (this.categoryDoughnutChart) {
            this.categoryDoughnutChart.destroy();
        }

        const labels = categories.map(c => c.name);
        const data = categories.map(c => Number(c.total_spent));
        const bgColors = categories.map(c => c.color || '#6366f1');

        if (data.length === 0 || data.every(v => v === 0)) {
            // Render empty placeholder
            ctx.parentNode.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i data-lucide="pie-chart"></i></div>
                    <div class="empty-state-title">No Expenses Recorded</div>
                    <div class="empty-state-text">Add your first expense transaction to see your spending breakdown.</div>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        this.categoryDoughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: bgColors,
                    borderWidth: 2,
                    borderColor: '#182234',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 12,
                            padding: 12,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const val = Number(context.raw);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ` ${context.label}: ${currency}${val.toLocaleString('en-IN', { minimumFractionDigits: 2 })} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    },

    // 3. 6-Month Expense Trend Line Chart
    initExpenseTrend(canvasId, trendData, currency = '₹') {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        this.applyDefaults();

        if (this.expenseTrendChart) {
            this.expenseTrendChart.destroy();
        }

        const labels = trendData.map(d => d.month);
        const incomeSeries = trendData.map(d => d.income);
        const expenseSeries = trendData.map(d => d.expenses);

        this.expenseTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeSeries,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Expenses',
                        data: expenseSeries,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#94a3b8', boxWidth: 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return ` ${context.dataset.label}: ${currency}${Number(context.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            color: '#94a3b8',
                            callback(value) {
                                return currency + Number(value).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    },

    // 4. Budget vs Actual Bar Chart
    initBudgetVsActual(canvasId, categoryBudgets, currency = '₹') {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        this.applyDefaults();

        if (this.budgetVsActualChart) {
            this.budgetVsActualChart.destroy();
        }

        // Filter categories that have either budget or actual spend
        const activeCats = categoryBudgets.filter(c => c.budget_amount > 0 || c.spent_amount > 0).slice(0, 8);
        const labels = activeCats.map(c => c.name);
        const budgets = activeCats.map(c => Number(c.budget_amount));
        const actuals = activeCats.map(c => Number(c.spent_amount));

        this.budgetVsActualChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Planned Budget',
                        data: budgets,
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Actual Spent',
                        data: actuals,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#94a3b8', boxWidth: 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return ` ${context.dataset.label}: ${currency}${Number(context.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', maxRotation: 45, minRotation: 0 }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            color: '#94a3b8',
                            callback(value) {
                                return currency + Number(value).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }
};
