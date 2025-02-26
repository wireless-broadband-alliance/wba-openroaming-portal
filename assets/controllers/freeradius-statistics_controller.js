import { Controller } from "@hotwired/stimulus";
import { Chart } from "chart.js";

export default class extends Controller {
    static targets = ["chart"];

    connect() {
        const target = this.chartTarget;
        if (!target) return console.error("Chart target not found!");

        // Determine the action from dataset (optional dynamic)
        const chartData = target.dataset.chartData;
        const chartType = target.id; // Chart type (example: "authAttemptsChart")

        // Initialize the chart
        this.initChart(target, chartData, chartType);
    }

    /**
     * Method to initialize a single chart
     * @param {HTMLElement} target - Canvas DOM element
     * @param {String} data - Chart data in JSON format
     * @param {String} chartType - Chart type or identifier
     */
    initChart(target, data, chartType) {
        console.log(`Initializing ${chartType} with data:`, JSON.parse(data));

        const chartData = JSON.parse(data);

        const chartOptions = {
            type: "bar", // Assume type "bar" by default
            data: chartData,
            options: {
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                scales: {
                    y: {
                        ticks: {
                            precision: 0,
                        },
                        display: false,
                    },
                },
            },
        };

        // Create and store the chart instance
        new Chart(target, chartOptions);
    }
}
