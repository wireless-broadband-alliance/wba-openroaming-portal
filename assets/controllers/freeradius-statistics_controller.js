import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
    static targets = ['chart'];

    // =========================
    // DESIGN SYSTEM
    // =========================
    colors = {
        primary: '#7DB928',
        danger: '#FE4068',
        info: '#3B82F6',
        success: '#10B981',
    };

    // automatic soft fills (important part)
    soft = {
        primary: 'rgba(125,185,40,0.12)',
        danger: 'rgba(254,64,104,0.12)',
        info: 'rgba(59,130,246,0.12)',
        success: 'rgba(16,185,129,0.12)',
    };

    connect() {
        const target = this.chartTarget;
        if (!target) return;

        const type = target.dataset.chartType || 'default';

        const handlers = {
            auth: this.renderAuthChart.bind(this),
            session: this.renderSessionChart.bind(this),
            sessionTotal: this.renderSessionTotalChart.bind(this),
            default: this.renderDefaultChart.bind(this),
        };

        (handlers[type] || handlers.default)(target);
    }

    // =========================
    // AUTH (FILLED LINE)
    // =========================
    renderAuthChart(target) {
        const raw = this.parseData(target);
        const labels = Object.keys(raw).sort();

        const accepted = labels.map(d => raw[d]?.accepted ?? 0);
        const rejected = labels.map(d => raw[d]?.rejected ?? 0);

        const data = {
            labels,
            datasets: [
                this.lineDataset('Accepted', accepted, 'primary'),
                this.lineDataset('Rejected', rejected, 'danger'),
            ]
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({tension: 0.3}),
        });
    }

    // =========================
    // SESSION AVERAGE
    // =========================
    renderSessionChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort((a, b) => new Date(a) - new Date(b));
        const values = labels.map(d => raw[d] ?? 0);

        const data = {
            labels,
            datasets: [
                this.lineDataset('Average Session Time', values, 'info'),
            ]
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({tension: 0.35, formatY: true}),
        });
    }

    // =========================
    // SESSION TOTAL (BAR)
    // =========================
    renderSessionTotalChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort((a, b) => new Date(a) - new Date(b));
        const values = labels.map(d => raw[d] ?? 0);

        const data = {
            labels,
            datasets: [
                this.barDataset('Total Session Time', values, 'success'),
            ]
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions({formatY: true}),
        });
    }

    // =========================
    // DEFAULT
    // =========================
    renderDefaultChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw);
        const values = Object.values(raw);

        const data = {
            labels,
            datasets: [
                this.barDataset('Data', values, 'primary'),
            ]
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions(),
        });
    }

    // =========================
    // LINE DATASET (AUTO-FILL)
    // =========================
    lineDataset(label, data, colorKey) {
        return {
            label,
            data,
            borderColor: this.colors[colorKey],
            backgroundColor: this.soft[colorKey], // 🔥 THIS is what gives the fill
            fill: true,

            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
        };
    }

    // =========================
    // BAR DATASET
    // =========================
    barDataset(label, data, colorKey) {
        return {
            label,
            data,
            backgroundColor: this.soft[colorKey],
            hoverBackgroundColor: this.colors[colorKey],
        };
    }

    // =========================
    // HELPERS
    // =========================
    parseData(target) {
        try {
            return JSON.parse(target.dataset.chartData || '{}');
        } catch (e) {
            console.error('Invalid JSON:', e);
            return {};
        }
    }

    createChart(target, config) {
        if (this.chartInstance) {
            this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(target, config);
    }

    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }

    // =========================
    // BASE OPTIONS
    // =========================
    baseOptions({
                    tension = 0,
                    formatY = false
                } = {}) {
        return {
            maintainAspectRatio: false,
            responsive: true,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: {
                    display: true,
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.raw;
                            if (typeof value !== 'number') return value;

                            return `${context.dataset.label}: ${this.formatDuration(value)}`;
                        }
                    }
                }
            },

            elements: {
                line: {
                    tension,
                    borderWidth: 2,
                },
                point: {
                    radius: 2,
                    hoverRadius: 5,
                },
            },

            scales: {
                x: {
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 7,
                    },
                    grid: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        callback: formatY ? (v) => this.formatDuration(v) : undefined,
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                    },
                },
            },
        };
    }
}
