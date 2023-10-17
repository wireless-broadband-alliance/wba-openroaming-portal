import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Show/hide icon on each radio button
		function initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio) {
			// Check which radio button is selected
			const onRadio = onLabel.parentElement.querySelector('input[type="radio"][value="true"]');
			const offRadio = offLabel.parentElement.querySelector('input[type="radio"][value="false"]');

			if (onRadio.checked) {
				onCustomRadio.classList.remove("hidden");
				offCustomRadio.classList.add("hidden");
			} else if (offRadio.checked) {
				offCustomRadio.classList.remove("hidden");
				onCustomRadio.classList.add("hidden");
			}

			onLabel.addEventListener("click", function () {
				onCustomRadio.classList.remove("hidden");
				offCustomRadio.classList.add("hidden");
			});

			offLabel.addEventListener("click", function () {
				offCustomRadio.classList.remove("hidden");
				onCustomRadio.classList.add("hidden");
			});
		}

		// JavaScript to display the correct icon when the page loads
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
