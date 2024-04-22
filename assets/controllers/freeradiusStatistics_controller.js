import {Controller} from '@hotwired/stimulus';
import {Chart} from 'chart.js';

export default class extends Controller {
	connect() {
		document.addEventListener('DOMContentLoaded', () => {
			// Get the chart elements for freeradius statistics from the radius database
			const authenticationAttempts = document.getElementById('authAttemptsChart');
			const realmsCounting = document.getElementById('realmsCountingChart');
			const currentAuths = document.getElementById('currentAuthsChart');
			const trafficPerRealmFreeradius = document.getElementById('trafficPerRealmFreeradiusChart');
			const sessionTimePerRealmFreeradius = document.getElementById('sessionTimePerRealmFreeradiusChart');

			if (authenticationAttempts && realmsCounting && currentAuths && trafficPerRealmFreeradius && sessionTimePerRealmFreeradius) {
				// Get the chart data from the data attributes on the elements
				const authAttemptsData = JSON.parse(authenticationAttempts.getAttribute('data-chart-data'));
				const realmsCountingData = JSON.parse(realmsCounting.getAttribute('data-chart-data'));
				const currentAuthsData = JSON.parse(currentAuths.getAttribute('data-chart-data'));
				const trafficPerRealmFreeradiusData = JSON.parse(trafficPerRealmFreeradius.getAttribute('data-chart-data'));
				const sessionTimePerRealmFreeradiusData = JSON.parse(sessionTimePerRealmFreeradius.getAttribute('data-chart-data'));

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

				const realmsCountingChart = new Chart(realmsCounting, {
					type: 'doughnut',
					data: realmsCountingData,
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
						radius: '75%', // Set the radius to make the Doughnut chart smaller
						animation: {
							animateRotate: true,
							animateScale: true,
						}
					},
				});

				const currentAuthsChart = new Chart(currentAuths, {
					type: 'pie',
					data: currentAuthsData,
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

				const trafficPerRealmFreeradiusChart = new Chart(trafficPerRealmFreeradius, {
					type: 'bar',
					data: trafficPerRealmFreeradiusData,
					options: {
						responsive: true,
						plugins: {
							legend: {
								display: false,
							},
						},
						animation: {
							animateRotate: true,
							animateScale: true,
						},
						indexAxis: 'y',
					},
				});

				const sessionTimePerRealmFreeradiusChart = new Chart(sessionTimePerRealmFreeradius, {
					type: 'bar',
					data: sessionTimePerRealmFreeradiusData,
					options: {
						responsive: true,
						plugins: {
							legend: {
								display: false,
							},
						},
						animation: {
							animateRotate: true,
							animateScale: true,
						},
						indexAxis: 'x',
					},
				});
			}
		});
	}
}
