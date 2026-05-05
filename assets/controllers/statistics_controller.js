import { Controller } from '@hotwired/stimulus';
import { Chart } from 'chart.js';

export default class extends Controller {
    static targets = ['chart'];

    // =========================
    // DESIGN SYSTEM
    // =========================
    colors = {
        primary: '#7DB928',
        danger:  '#FE4068',
        info:    '#38A2AE',
    };

    palette = [
        { solid: '#7DB928', soft: 'rgba(125,185,40,0.15)'  },  // 0 — green
        { solid: '#8A63FF', soft: 'rgba(138,99,255,0.15)'  },  // 1 — purple
        { solid: '#38A2AE', soft: 'rgba(56,162,174,0.15)'  },  // 2 — teal
        { solid: '#F59E0B', soft: 'rgba(245,158,11,0.15)'  },  // 3 — amber
        { solid: '#FE4068', soft: 'rgba(254,64,104,0.15)'  },  // 4 — pink/red
        { solid: '#10B981', soft: 'rgba(16,185,129,0.15)'  },  // 5 — emerald
    ];

    // =========================
    // CONNECT
    // =========================
    connect() {
        const target = this.chartTarget;
        if (!target) return console.error('Chart target not found!');

        const style = target.dataset.chartStyle || '';

        const handlers = {
            'sms-email':       () => this.renderDoughnutChart(target, 'sms-email'),
            'authentication':  () => this.renderDoughnutChart(target, 'authentication'),
            'devices':         () => this.renderVerticalBarChart(target),
            'platform-status': () => this.renderHorizontalBarChart(target),
            'users-verified':  () => this.renderHorizontalBarChart(target),
            '2fa':             () => this.renderHorizontalBarChart(target),
        };

        const handler = handlers[style] || (() => this.renderDefaultChart(target));
        handler();
    }

    // =========================
    // CHART RENDERERS
    // =========================

    /**
     * Shared doughnut renderer — used by all doughnut cards.
     * dotPrefix matches the CSS class prefix in Twig e.g. "sms-email" → ".sms-email-dot-0"
     */
    renderDoughnutChart(canvas, dotPrefix) {
        canvas.width  = 200;
        canvas.height = 200;

        const parsedData    = this.parseData(canvas);
        const labels        = parsedData.labels ?? [];
        const values        = parsedData.datasets?.[0]?.data ?? [];
        const total         = parsedData.total ?? 0;
        const segmentColors = labels.map((_, i) => this.palette[i % this.palette.length].solid);

        const dominantIndex = values.indexOf(Math.max(...values));
        const dominantPct   = total > 0 ? Math.round((values[dominantIndex] / total) * 100) : 0;
        const dominantLabel = labels[dominantIndex] ?? '';

        // Paint legend dots
        segmentColors.forEach((color, i) => {
            const dot = document.querySelector(`.${dotPrefix}-dot-${i}`);
            if (dot) dot.style.background = color;
        });

        const centerLabelPlugin = {
            id: `centerLabel-${dotPrefix}`,
            afterDraw(chart) {
                const { ctx, chartArea: { top, bottom, left, right } } = chart;
                const cx = (left + right) / 2;
                const cy = (top + bottom) / 2;
                ctx.save();
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.font         = 'bold 1.4rem sans-serif';
                ctx.fillStyle    = '#111';
                ctx.fillText(`${dominantPct}%`, cx, cy - 10);
                ctx.font      = '0.75rem sans-serif';
                ctx.fillStyle = '#6b7280';
                ctx.fillText(`use ${dominantLabel}`, cx, cy + 12);
                ctx.restore();
            },
        };

        this.createChart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: segmentColors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    borderRadius: 2,
                    spacing: 1,
                }],
            },
            options: {
                cutout: '75%',
                maintainAspectRatio: false,
                plugins: {
                    legend:  { display: false },
                    tooltip: { enabled: false },
                },
            },
            plugins: [centerLabelPlugin],
        });
    }

    /**
     * Vertical bar chart
     */
    renderVerticalBarChart(target) {
        const parsedData = this.parseData(target);

        if (parsedData.datasets) {
            parsedData.datasets.forEach((dataset, i) => {
                const entry = this.palette[i % this.palette.length];
                dataset.backgroundColor      = entry.soft;
                dataset.hoverBackgroundColor = entry.solid;
                dataset.borderRadius         = 4;
            });
        }

        this.createChart(target, {
            type: 'bar',
            data: parsedData,
            options: this.verticalBarOptions(),
        });
    }

    /**
     * Horizontal bar chart
     */
    renderHorizontalBarChart(target) {
        const parsedData = this.parseData(target);

        if (parsedData.datasets) {
            parsedData.datasets.forEach((dataset, i) => {
                const entry = this.palette[i % this.palette.length];
                dataset.backgroundColor      = entry.soft;
                dataset.hoverBackgroundColor = entry.solid;
                dataset.borderRadius         = 4;
            });
        }

        this.createChart(target, {
            type: 'bar',
            data: parsedData,
            options: this.horizontalBarOptions(),
        });
    }

    /**
     * Fallback
     */
    renderDefaultChart(target) {
        const parsedData   = this.parseData(target);
        const chartType    = target.dataset.chartType || 'bar';
        const isHorizontal = target.dataset.indexAxis === 'y';

        if (parsedData.datasets) {
            parsedData.datasets.forEach((dataset, i) => {
                const entry = this.palette[i % this.palette.length];
                dataset.backgroundColor      = entry.soft;
                dataset.hoverBackgroundColor = entry.solid;
                dataset.borderRadius         = 4;
            });
        }

        this.createChart(target, {
            type: chartType,
            data: parsedData,
            options: isHorizontal ? this.horizontalBarOptions() : this.verticalBarOptions(),
        });
    }

    // =========================
    // CHART OPTIONS
    // =========================
    verticalBarOptions() {
        return {
            maintainAspectRatio: false,
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                },
                x: { grid: { display: false } },
            },
        };
    }

    horizontalBarOptions() {
        return {
            maintainAspectRatio: false,
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                },
                y: { grid: { display: false } },
            },
        };
    }

    // =========================
    // HELPERS
    // =========================
    parseData(target) {
        try {
            return JSON.parse(target.dataset.chartData || '{}');
        } catch (e) {
            console.error('Invalid chart JSON:', e);
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
}
