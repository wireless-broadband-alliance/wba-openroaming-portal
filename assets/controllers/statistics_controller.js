import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
	connect() {
		document.addEventListener('DOMContentLoaded', () => {
			// Get the chart elements for devices and authentication
			const devicesChartElement = document.getElementById('devicesChart');
			const authenticationChartElement = document.getElementById('authenticationChart');
			const platformStatusChartElement = document.getElementById('platformStatusChart');


			if (devicesChartElement && authenticationChartElement && platformStatusChartElement) {
				// Get the chart data from the data attributes on the elements
				const devicesData = JSON.parse(devicesChartElement.getAttribute('data-chart-data'));
				const authenticationData = JSON.parse(authenticationChartElement.getAttribute('data-chart-data'));
				const platformStatusData = JSON.parse(platformStatusChartElement.getAttribute('data-chart-data'));

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

				const platformStatusChart = new Chart(platformStatusChartElement, {
					type: 'bar',
					data: platformStatusData,
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
