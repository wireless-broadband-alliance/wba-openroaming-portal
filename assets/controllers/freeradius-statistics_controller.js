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
        info: '#3B82F6',
        success: '#10B981',
    };

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
    // AUTH
    // =========================
    renderAuthChart(target) {
        const raw = this.parseData(target);
        const labels = Object.keys(raw).sort();

        const accepted = labels.map((d) => raw[d]?.accepted ?? 0);
        const rejected = labels.map((d) => raw[d]?.rejected ?? 0);

        const data = {
            labels,
            datasets: [
                this.lineDataset('Accepted', accepted, 'primary', false),
                this.lineDataset('Rejected', rejected, 'danger', false),
            ],
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({ tension: 0.3, isDuration: false }),
        });
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
            options: this.baseOptions({ tension: 0.35, isDuration: true }),
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
            datasets: [this.barDataset('Total Session Time', values, 'success', true)],
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions({ isDuration: true }),
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
            pointRadius: 2,
            pointHoverRadius: 5,
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
    }

    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }

    // =========================
    // BASE OPTIONS
    // =========================
    baseOptions({ tension = 0, isDuration = false } = {}) {
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
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                    },
                },
            },
        };
    }
}
