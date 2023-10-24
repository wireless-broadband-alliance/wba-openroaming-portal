import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Show/hide icon on each radio button
		function initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio) {
			// Check which radio button is selected
			const onRadio = onLabel.parentElement.querySelector('input[type="radio"][value="Demo"]');
			const offRadio = offLabel.parentElement.querySelector('input[type="radio"][value="Live"]');
			const onRadioEmail = onLabel.parentElement.querySelector('input[type="radio"][value="ON"]');
			const offRadioEmail = offLabel.parentElement.querySelector('input[type="radio"][value="OFF"]');

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

			if (onRadioEmail && offRadioEmail && onCustomRadio && offCustomRadio) {
				if (onRadioEmail.checked) {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				} else if (offRadioEmail.checked) {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				}

				onRadioEmail.addEventListener("change", function () {
					onCustomRadio.classList.remove("hidden");
					offCustomRadio.classList.add("hidden");
				});

				offRadioEmail.addEventListener("change", function () {
					offCustomRadio.classList.remove("hidden");
					onCustomRadio.classList.add("hidden");
				});
			}
		}

		document.addEventListener("DOMContentLoaded", function () {
			const radioSets = document.querySelectorAll('[id="PLATFORM_MODE"]');

			radioSets.forEach(function (radioSet) {
				// Check if it's the first set of radio buttons
				const onLabel = radioSet.querySelector('[name="onLabel"]');
				const offLabel = radioSet.querySelector('[name="offLabel"]');
				const onCustomRadio = radioSet.querySelector('[name="onCustomRadio"]');
				const offCustomRadio = radioSet.querySelector('[name="offCustomRadio"]');

				initializeRadioButtons(onLabel, offLabel, onCustomRadio, offCustomRadio);
			});
		});

		document.addEventListener("DOMContentLoaded", function () {
			const radioSetsEmail = document.querySelectorAll('[id="EMAIL_VERIFICATION"]');

			radioSetsEmail.forEach(function (radioSet) {
				const onLabelEmail = radioSet.querySelector('[name="onLabelEmail"]');
				const offLabelEmail = radioSet.querySelector('[name="offLabelEmail"]');
				const onCustomRadioEmail = radioSet.querySelector('[name="onCustomRadioEmail"]');
				const offCustomRadioEmail = radioSet.querySelector('[name="offCustomRadioEmail"]');

				initializeRadioButtons(onLabelEmail, offLabelEmail, onCustomRadioEmail, offCustomRadioEmail);
			});
		});

		document.addEventListener("DOMContentLoaded", function () {
			const platformModeRadios = document.querySelectorAll('[name="status[PLATFORM_MODE]"]');
			const emailVerificationCard = document.getElementById("EMAIL_VERIFICATION");
			const statusMessage = document.getElementById('statusMessage');

			function updateEmailVerificationCard() {
				let selectedValue = null;
				platformModeRadios.forEach(function (radio) {
					if (radio.checked) {
						selectedValue = radio.value;
					}
				});

				const formElements = emailVerificationCard.querySelectorAll("input, select, textarea");

				if (selectedValue === "Live") {
					// Disable all form elements
					formElements.forEach(function (element) {
						element.disabled = true;
					});
					statusMessage.classList.remove('hidden');
				} else {
					// Enable all form elements
					formElements.forEach(function (element) {
						element.disabled = false;
					});
					statusMessage.classList.add('hidden');
				}
			}

			updateEmailVerificationCard();

			platformModeRadios.forEach(function (radio) {
				radio.addEventListener("change", updateEmailVerificationCard);
			});
		});
	}
}
