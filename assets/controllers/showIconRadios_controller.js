import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Show/hide icon on each radio button
		function initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio) {
			// Check which radio button is selected
			const onRadio = document.getElementById('onRadio');
			const offRadio = document.getElementById('offRadio');

			if (onRadio && offRadio && onCustomRadio && offCustomRadio) {
				if (onRadio.checked) {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				} else if (offRadio.checked) {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				}

				onRadio.addEventListener("change", function () {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				});

				offRadio.addEventListener("change", function () {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				});
			}
		}

		// Looks for the first element with the name declared
		document.addEventListener("DOMContentLoaded", function () {
			const radioSets = document.querySelectorAll('[name="Cards"]');

			radioSets.forEach(function (radioSet) {
				const onLabel = radioSet.querySelector('[name="onLabel"]');
				const offLabel = radioSet.querySelector('[name="offLabel"]');
				const onCustomRadio = radioSet.querySelector('[name="onCustomRadio"]');
				const offCustomRadio = radioSet.querySelector('[name="offCustomRadio"]');

				initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio);
			});
		});
	}
}
