import {Controller} from '@hotwired/stimulus';
import {Chart} from "chart.js";

export default class extends Controller {
	connect() {
		// Get the chart element
		const chartElement = this.element;

		// Get the chart data from the data attribute on the twig file - statistics.html.twig
		const chartData = JSON.parse(chartElement.getAttribute('data-chart-data'));

		// Create the Chart.js chart with the fetched data on the AdminController.php
		const chart = new Chart(chartElement, {
			type: 'line',
			data: chartData,
			options: {
				scales: {
					y: {
						suggestedMin: 0,
						suggestedMax: 100,
					},
				},
			},
		});
	}
}
