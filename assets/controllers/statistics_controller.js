import {Controller} from "@hotwired/stimulus";
import {Chart} from "chart.js";

export default class extends Controller {
    static targets = ["chart"];

    /**
     * Called when the controller connects to the DOM.
     * Automatically initializes the chart for the connected canvas element.
     */
    connect() {
        const target = this.chartTarget;
        if (!target) return console.error("Chart target not found!");

        const chartData = target.dataset.chartData;
        const chartType = target.dataset.chartType || "bar";  // Default to type "bar" if not specified

        // Initialize the chart
        this.initChart(target, chartData, chartType);
    }

    /**
     * Initializes a Chart.js chart with the given configuration.
     * @param {HTMLElement} target - The canvas element
     * @param {String} data - The chart data in JSON format
     * @param {String} chartType - The chart type (e.g., "bar", "line")
     */
    initChart(target, data, chartType) {
        console.log(`Initializing chart of type "${chartType}" for:`, target);

        const parsedData = JSON.parse(data);

        // Default chart options
        const chartOptions = {
            type: chartType, // Chart type (e.g., "bar")
            data: parsedData,
            options: {
                plugins: {
                    legend: {
                        display: false, // Hide the legend (labels at the top)
                    },
                },
                scales: {
                    y: {
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        };

        // Dynamic handling for horizontal/vertical bar charts
        if (target.dataset.indexAxis === "y") { // Check if indexAxis is set to y
            chartOptions.options.indexAxis = "y"; // Set the bar chart as horizontal
            chartOptions.options.scales = {
                x: {ticks: {precision: 0}}, // Customize for horizontal bars
            };
        }

        // Create the chart
        new Chart(target, chartOptions);
    }
}
