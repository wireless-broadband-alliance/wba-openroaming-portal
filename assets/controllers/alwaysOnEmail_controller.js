import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		document.addEventListener("DOMContentLoaded", function () {
			const platformModeRadios = document.querySelectorAll('[name="status[PLATFORM_MODE]"]');
			const userVerificationCard = document.getElementById("USER_VERIFICATION");

			function updateEmailVerificationCard() {
				let selectedValue = null;
				platformModeRadios.forEach(function (radio) {
					if (radio.checked) {
						selectedValue = radio.value;
					}
				});

				const formElements = userVerificationCard.querySelectorAll("input, select, textarea");

				if (selectedValue === "Live") {
					// Disable all form elements
					formElements.forEach(function (element) {
						element.disabled = true;
					});
					userVerificationCard.classList.remove('bg-white');
					userVerificationCard.classList.add('bg-disableCardsColor');
				} else {
					// Enable all form elements
					formElements.forEach(function (element) {
						element.disabled = false;
					});
					userVerificationCard.classList.add('bg-white');
					userVerificationCard.classList.remove('bg-disableCardsColor');
				}
			}

			updateEmailVerificationCard();

			platformModeRadios.forEach(function (radio) {
				radio.addEventListener("change", updateEmailVerificationCard);
			});
		});

	}
}
