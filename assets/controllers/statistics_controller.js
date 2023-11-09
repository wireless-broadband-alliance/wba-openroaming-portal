import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
	connect() {
		document.addEventListener('DOMContentLoaded', () => {
			// Get the chart elements for devices and authentication
			const devicesChartElement = document.getElementById('devicesChart');
			const authenticationChartElement = document.getElementById('authenticationChart');
			const platformStatusChartElement = document.getElementById('platformStatusChart');
			const userVerifiedChartElement = document.getElementById('userVerifiedChart');


			if (devicesChartElement && authenticationChartElement && platformStatusChartElement && userVerifiedChartElement) {
				// Get the chart data from the data attributes on the elements
				const devicesData = JSON.parse(devicesChartElement.getAttribute('data-chart-data'));
				const authenticationData = JSON.parse(authenticationChartElement.getAttribute('data-chart-data'));
				const platformStatusData = JSON.parse(platformStatusChartElement.getAttribute('data-chart-data'));
				const userVerifiedData = JSON.parse(userVerifiedChartElement.getAttribute('data-chart-data'));

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
						scales: {
							y: {
								ticks: {
									precision: 0
								}
							}
						}
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
						scales: {
							y: {
								ticks: {
									precision: 0
								}
							}
						}
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
						scales: {
							x: {
								ticks: {
									precision: 0
								}
							}
						},
						indexAxis: 'y',
					}
				});

				const userVerifiedChart = new Chart(userVerifiedChartElement, {
					type: 'bar',
					data: userVerifiedData,
					options: {
						plugins: {
							legend: {
								display: false,
							},
						},
						scales: {
							x: {
								ticks: {
									precision: 0
								}
							}
						},
						indexAxis: 'y',
					}
				});
			}
		});
	}
}
