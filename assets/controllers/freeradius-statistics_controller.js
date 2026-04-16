import { Controller } from '@hotwired/stimulus';
import { Chart } from 'chart.js';

export default class extends Controller {
    static targets = ['chart'];

    connect() {
        const target = this.chartTarget;
        if (!target) return;

        const type = target.dataset.chartType || 'default';

        const handlers = {
            auth: this.renderAuthChart.bind(this),
            session: this.renderSessionChart.bind(this),
            default: this.renderDefaultChart.bind(this),
        };

        const handler = handlers[type] || handlers.default;
        handler(target);
    }

    // =========================
    // AUTH (FIXED)
    // =========================
    renderAuthChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort();

        const accepted = labels.map(date => raw[date]?.accepted ?? 0);
        const rejected = labels.map(date => raw[date]?.rejected ?? 0);

        const data = {
            labels,
            datasets: [
                {
                    label: 'Accepted',
                    data: accepted,
                    borderColor: '#7DB928',
                    backgroundColor: 'rgba(125,185,40,0.2)',
                    fill: true,
                },
                {
                    label: 'Rejected',
                    data: rejected,
                    borderColor: '#FE4068',
                    backgroundColor: 'rgba(254,64,104,0.2)',
                    fill: true,
                }
            ]
        };

        this.createChart(target, {
            type: 'line',
            data,
            options: this.baseOptions({
                tension: 0.3,
                yBeginAtZero: true,
            }),
        });
    }

    // =========================
    // SESSION (generic example)
    // =========================
    renderSessionChart(target) {
        const raw = this.parseData(target);

        const labels = Object.keys(raw).sort();
        const values = labels.map(date => raw[date] ?? 0);

        const data = {
            labels,
            datasets: [
                {
                    label: 'Sessions',
                    data: values,
                    borderWidth: 1,
                }
            ]
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions({
                yBeginAtZero: true,
            }),
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
                {
                    data: values,
                }
            ]
        };

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions(),
        });
    }

    // =========================
    // HELPERS
    // =========================
    parseData(target) {
        try {
            return JSON.parse(target.dataset.chartData || '{}');
        } catch (e) {
            console.error('Invalid JSON in chart data:', e);
            return {};
        }
    }

    createChart(target, config) {
        if (this.chartInstance) {
            this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(target, config);
    }

    baseOptions({ tension = 0, yBeginAtZero = true } = {}) {
        return {
            maintainAspectRatio: false,
            responsive: true,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: {
                    display: true, // turned ON for auth chart clarity
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
                    beginAtZero: yBeginAtZero,
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