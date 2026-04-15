import { Controller } from '@hotwired/stimulus';
import { Chart } from 'chart.js';

export default class extends Controller {
    static targets = ['chart'];

    connect() {
        const target = this.chartTarget;
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
    // SESSION
    // =========================
    renderSessionChart(target) {
        const data = JSON.parse(target.dataset.chartData);

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions({
                tension: 0.35,
                yBeginAtZero: true,
            }),
        });
    }

    // =========================
    // AUTH
    // =========================
    renderAuthChart(target) {
        const data = JSON.parse(target.dataset.chartData);

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
    // DEFAULT
    // =========================
    renderDefaultChart(target) {
        const data = JSON.parse(target.dataset.chartData);

        this.createChart(target, {
            type: 'bar',
            data,
            options: this.baseOptions(),
        });
    }

    // =========================
    // CORE FACTORY
    // =========================
    createChart(target, config) {
        if (this.chartInstance) {
            this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(target, config);
    }

    // =========================
    // BASE OPTIONS
    // =========================
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
                    display: false,
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