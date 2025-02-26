import { Controller } from "@hotwired/stimulus";
import { Chart } from "chart.js";

export default class extends Controller {
    connect() {
        document.addEventListener("DOMContentLoaded", () => {
            // Get the chart elements for freeradius statistics from the radius database
            const authenticationAttempts = document.getElementById("authAttemptsChart");
            const sessionTime = document.getElementById("sessionTimeChart");
            const totalTime = document.getElementById("totalTimeChart");
            const wifiTags = document.getElementById("wifiTagsChart");

            if (authenticationAttempts && sessionTime && totalTime && wifiTags) {
                // Get the chart data from the data attributes on the elements
                const authAttemptsData = JSON.parse(authenticationAttempts.getAttribute("data-chart-data"));
                const sessionTimeData = JSON.parse(sessionTime.getAttribute("data-chart-data"));
                const totalTimeData = JSON.parse(totalTime.getAttribute("data-chart-data"));
                const wifiTagsData = JSON.parse(wifiTags.getAttribute("data-chart-data"));

                // Create the Chart.js charts with the fetched data about the freeradius content
                const authAttemptsChart = new Chart(authenticationAttempts, {
                    type: "bar",
                    data: authAttemptsData,
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
                                display: false,
                            },
                        },
                    },
                });

                const averageTimeChart = new Chart(sessionTime, {
                    type: "bar",
                    data: sessionTimeData,
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const index = context.dataIndex;
                                        const dataset = context.dataset;
                                        const value = dataset.data[index];
                                        const tooltip = dataset.tooltips[index];
                                        return tooltip; // Display human-readable format
                                    },
                                },
                            },
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
                });

                const totalTimeChart = new Chart(totalTime, {
                    type: "bar",
                    data: totalTimeData,
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const index = context.dataIndex;
                                        const dataset = context.dataset;
                                        const value = dataset.data[index];
                                        const tooltip = dataset.tooltips[index];
                                        return tooltip;
                                    },
                                },
                            },
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
                });

                const wifiTagsChart = new Chart(wifiTags, {
                    type: "bar",
                    data: wifiTagsData,
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
                            },
                        },
                    },
                });
            }
        });
    }
}
