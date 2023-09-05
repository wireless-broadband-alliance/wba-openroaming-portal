/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// Used for chart.js graphic on the statistics page
import { Chart } from 'chart.js';

// start the Stimulus application
import './bootstrap';

// Initialization for ES Users
import {
	Dropdown,
	Ripple,
	initTE,
} from "tw-elements";


initTE({Dropdown, Ripple});

/*
let config = {
	type: "line",
	data: {
		labels: [
			"January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"
		],
		datasets: [
			{
				label: "{{ current_year }}",
				backgroundColor: "#0ea5e9",
				borderColor: "#0ea5e9",
				data: [65, 78, 66, 44, 56, 67, 75],
				fill: false,
			},
			{
				label: "{{ previous_year }}",
				fill: false,
				backgroundColor: "#A0C66B",
				borderColor: "#A0C66B",
				data: [40, 68, 86, 74, 56, 60, 87],
			},
		],
	},
	options: {
		maintainAspectRatio: false,
		responsive: true,
		title: {
			display: false,
			text: "Dynamic Graphic",
			fontColor: "black",
		},
		legend: {
			labels: {
				fontColor: "black",
			},
			align: "end",
			position: "top",
		},
		tooltips: {
			mode: "index",
			intersect: false,
		},
		hover: {
			mode: "nearest",
			intersect: true,
		},
		scales: {
			xAxes: [
				{
					ticks: {
						fontColor: "rgba(0,0,0,0.7)",
					},
					display: true,
					scaleLabel: {
						display: false,
						labelString: "Month",
						fontColor: "black",
					},
					gridLines: {
						display: false,
						borderDash: [2],
						borderDashOffset: [2],
						color: "rgba(33, 37, 41, 0.3)",
						zeroLineColor: "rgba(0, 0, 0, 0)",
						zeroLineBorderDash: [2],
						zeroLineBorderDashOffset: [2],
					},
				},
			],
			yAxes: [
				{
					ticks: {
						fontColor: "rgba(0,0,0,0.7)",
					},
					display: true,
					scaleLabel: {
						display: false,
						labelString: "Value",
						fontColor: "black",
					},
					gridLines: {
						borderDash: [3],
						borderDashOffset: [3],
						drawBorder: false,
						color: "rgba(255, 255, 255, 0.15)",
						zeroLineColor: "rgba(33, 37, 41, 0)",
						zeroLineBorderDash: [2],
						zeroLineBorderDashOffset: [2],
					},
				},
			],
		},
	},
};
let ctx = document.getElementById("line-chart").getContext("2d");
window.myLine = new Chart(ctx, config);
*/
