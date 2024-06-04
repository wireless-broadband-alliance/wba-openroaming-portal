import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
	connect() {
		document.addEventListener('DOMContentLoaded', () => {
			// Get the chart elements for freeradius statistics from the radius database
			const authenticationAttempts = document.getElementById('authAttemptsChart');

			if (authenticationAttempts) {
				// Get the chart data from the data attributes on the elements
				const authAttemptsData = JSON.parse(authenticationAttempts.getAttribute('data-chart-data'));

				// Create the Chart.js charts with the fetched data about the freeradius content
				const authAttemptsChart = new Chart(authenticationAttempts, {
					type: 'bar',
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
									precision: 0
								}
							}
						}
					}
				});
			}
		});
	}
}
