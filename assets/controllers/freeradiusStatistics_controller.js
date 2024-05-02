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
					type: 'pie',
					data: authAttemptsData,
					options: {
						responsive: true,
						plugins: {
							legend: {
								display: false, // Hide the legend (labels at the top)
							},
						},
						scales: {
							x: {
								display: false, // Hide the x-axis
							},
							y: {
								display: false, // Hide the y-axis
							},
						},
						radius: '75%', // Set the radius to make the pie chart smaller
						animation: {
							animateRotate: true,
							animateScale: true,
						}
					},
				});
			}
		});
	}
}
