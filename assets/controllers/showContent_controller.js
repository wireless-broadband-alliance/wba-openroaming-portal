import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Function to initialize the show/hide functionality for radio buttons
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
		
		// Display the capport message
		function showMessageCapport() {
			const capportRadioButtons = document.querySelectorAll('[name="capport[CAPPORT_ENABLED]"]');
			const capportMessage = document.getElementById('capportMessage');

			if (capportRadioButtons) {
				const toggleMessageState = () => {
					const capportEnabledValue = document.querySelector('[name="capport[CAPPORT_ENABLED]"]:checked').value;

					if (capportEnabledValue === 'true') {
						capportMessage.classList.remove('hidden');
					} else {
						capportMessage.classList.add('hidden');
					}
				};

				// Attach input event listener to all radio buttons
				capportRadioButtons.forEach(radioButton => {
					radioButton.addEventListener('input', toggleMessageState);
				});

				toggleMessageState(); // Call it initially to handle the default state
			}
		}

		showMessageCapport();
	}
}
