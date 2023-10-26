import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
	connect() {
		document.addEventListener('DOMContentLoaded', () => {
			// Get the chart elements for devices and authentication
			const devicesChartElement = document.getElementById('devicesChart');
			const authenticationChartElement = document.getElementById('authenticationChart');

			if (devicesChartElement && authenticationChartElement) {
				// Get the chart data from the data attributes on the elements
				const devicesData = JSON.parse(devicesChartElement.getAttribute('data-chart-data'));
				const authenticationData = JSON.parse(authenticationChartElement.getAttribute('data-chart-data'));

				// Create the Chart.js charts with the fetched data for devices and authentication
				const devicesChart = new Chart(devicesChartElement, {
					type: 'bar',
					data: devicesData,
					options: {
						plugins: {
							legend: {
								display: false, // Hide the legend (labels at the top)
							},
						},
					}
				});

				const authenticationChart = new Chart(authenticationChartElement, {
					type: 'bar',
					data: authenticationData,
					options: {
						plugins: {
							legend: {
								display: false,
							},
						},
					}
				});
			}
		});
	}
}
