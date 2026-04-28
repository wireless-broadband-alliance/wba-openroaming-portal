import { Controller } from '@hotwired/stimulus';
import { Chart } from 'chart.js';

export default class extends Controller {
    static targets = ['chart'];

    // =========================
    // DESIGN SYSTEM
    // =========================
    colors = {
        primary: '#7DB928',
        danger: '#FE4068',
        info: '#38A2AE',
        success: '#10B981',
    };

    soft = {
        primary: 'rgba(125,185,40,0.12)',
        danger: 'rgba(254,64,104,0.12)',
        info: 'rgba(238, 246, 247)',
        success: 'rgba(16,185,129,0.12)',
    };

    static chartRegistry = {};

    connect() {
        const target = this.chartTarget;
        if (!target) return;

        const type = target.dataset.chartType || 'default';

        const handlers = {
            accepted: this.renderAcceptedChart.bind(this),
            rejected: this.renderRejectedChart.bind(this),
            session: this.renderSessionChart.bind(this),
            sessionTotal: this.renderSessionTotalChart.bind(this),
            wifiTags: this.renderWifiTagsChart.bind(this),
            default: this.renderDefaultChart.bind(this),
        };

        (handlers[type] || handlers.default)(target);
    }

    // =========================
    // AUTH
    // =========================
    // ACCEPTED (split top)
    renderAcceptedChart(target) {
        const raw = this.parseData(target);
        const labels = Object.keys(raw).sort();
        const accepted = labels.map((d) => raw[d]?.accepted ?? 0);

        const chart = this.createChart(target, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    this.lineDataset(target.dataset.labelAccepted, accepted, 'primary', false),
                ],
            },
            options: this.splitChartOptions(),
        });

        this.constructor.chartRegistry['accepted'] = chart;
        this.bindSyncedHover(target, 'accepted', 'rejected');
    }

    // REJECTED (split bottom)
    renderRejectedChart(target) {
        const raw = this.parseData(target);
        const labels = Object.keys(raw).sort();
        const rejected = labels.map((d) => raw[d]?.rejected ?? 0);

        const chart = this.createChart(target, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    this.lineDataset(target.dataset.labelRejected, rejected, 'danger', false),
                ],
            },
            options: this.splitChartOptions(),
        });

        this.constructor.chartRegistry['rejected'] = chart;
        this.bindSyncedHover(target, 'rejected', 'accepted');
    }

    // =========================
    // SESSION AVERAGE
    // =========================
    renderSessionChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort((a, b) => new Date(a) - new Date(b));
        const values = labels.map((d) => raw[d] ?? 0);

        const data = {
            labels,
            datasets: [this.lineDataset('Average Session Time', values, 'info', true)],
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({ tension: 0.35, isDuration: true, minimal: true }),
        });
    }

    // =========================
    // SESSION TOTAL
    // =========================
    renderSessionTotalChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort((a, b) => new Date(a) - new Date(b));
        const values = labels.map((d) => raw[d] ?? 0);

        const data = {
            labels,
            datasets: [this.lineDataset('Total Session Time', values, 'info', true)],
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({ tension: 0.35, isDuration: true, minimal: true }),
        });
    }

    // =========================
    // WIFI Tags
    // =========================
    renderWifiTagsChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw);
        const values = Object.values(raw);

        const total = values.reduce((a, b) => a + b, 0);

        const data = {
            labels,
            datasets: [
                {
                    data: values,

                    backgroundColor: [
                        this.colors.primary,
                        this.colors.info,
                        this.colors.success,
                        this.colors.danger,
                    ],

                    hoverOffset: 8,
                    borderWidth: 2,
                },
            ],
        };

        this.createChart(target, {
            type: 'doughnut',
            data,
            options: {
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        position: 'bottom',
                    },

                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.raw;
                                const percent = ((value / total) * 100).toFixed(1);

                                return `${context.label}: ${value} (${percent}%)`;
                            },
                        },
                    },
                },
            },
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
            datasets: [this.barDataset('Data', values, 'primary', false)],
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions({ isDuration: false }),
        });
    }

    // =========================
    // DATASETS
    // =========================
    lineDataset(label, data, colorKey, isDuration = false) {
        return {
            label,
            data,
            borderColor: this.colors[colorKey],
            backgroundColor: this.soft[colorKey],
            fill: true,
            borderWidth: 2,
            pointRadius: 0, // no dots at rest
            pointHoverRadius: 5, // dot appears on hover
            meta: { isDuration },
        };
    }

    barDataset(label, data, colorKey, isDuration = false) {
        return {
            label,
            data,
            backgroundColor: this.soft[colorKey],
            hoverBackgroundColor: this.colors[colorKey],
            meta: { isDuration },
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
        return this.chartInstance;
    }

    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }

    bindSyncedHover(target, selfKey, otherKey) {
        target.addEventListener('mousemove', (e) => {
            const self = this.constructor.chartRegistry[selfKey];
            const other = this.constructor.chartRegistry[otherKey];
            if (!self || !other) return;

            const points = self.getElementsAtEventForMode(e, 'index', { intersect: false }, true);
            if (!points.length) return;

            const index = points[0].index;
            other.tooltip.setActiveElements(
                other.data.datasets.map((_, di) => ({ datasetIndex: di, index })),
                { x: 0, y: 0 }
            );
            other.setDatasetVisibility(0, true);
            other.update('none');
        });

        target.addEventListener('mouseleave', () => {
            const other = this.constructor.chartRegistry[otherKey];
            if (!other) return;
            other.tooltip.setActiveElements([], {});
            other.update('none');
        });
    }

    splitChartOptions() {
        return {
            maintainAspectRatio: false,
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false }, // removes the colored box label only
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.dataset.label}: ${context.raw}`,
                    },
                },
            },
            elements: {
                line: { tension: 0.3, borderWidth: 2 },
                point: { radius: 0, hoverRadius: 5 },
            },
            scales: {
                x: {
                    ticks: {
                        display: true, // keeps date labels
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 7,
                    },
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        display: true, // keeps number labels
                        precision: 0,
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                },
            },
        };
    }

    // =========================
    // BASE OPTIONS
    // =========================
    baseOptions({ tension = 0, isDuration = false, minimal = false } = {}) {
        return {
            maintainAspectRatio: false,
            responsive: true,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: {
                    display: !minimal,
                },

                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.raw;
                            const datasetIsDuration =
                                context.dataset.meta?.isDuration ?? isDuration;

                            if (typeof value !== 'number') return value;

                            if (datasetIsDuration) {
                                return `${context.dataset.label}: ${this.formatDuration(value)}`;
                            }

                            return `${context.dataset.label}: ${value}`;
                        },
                    },
                },
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
                        display: !minimal,
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
                        display: !minimal,
                        precision: 0,
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                    },
                },
            },
        };
    }
}
