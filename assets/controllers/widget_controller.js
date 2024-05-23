import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["modal"];

	connect() {
		function checkInputsEnableButtons(buttonId) {
			document.addEventListener("DOMContentLoaded", function () {
				const button = document.getElementById(buttonId);
				const inputs = document.querySelectorAll("input");

				// Store the initial values each input
				const initialValues = {};
				inputs.forEach(function (input) {
					initialValues[input.id] = input.value;
				});

				function areInputsRestored() {
					let restored = true;
					inputs.forEach(function (input) {
						if (input.value !== initialValues[input.id]) {
							restored = false;
						}
					});
					return restored;
				}

				inputs.forEach(function (input) {
					input.addEventListener("input", function () {
						if (!areInputsRestored()) {
							button.removeAttribute("disabled");
							button.classList.remove("cursor-not-allowed");
						} else {
							button.setAttribute("disabled", "disabled");
							button.classList.add("cursor-not-allowed");
						}
					});
				});
			});
		}
		checkInputsEnableButtons('widgetConfirmAccount');

	}

	open() {
		this.modalTarget.classList.remove('hidden');
	}

	close() {
		this.modalTarget.classList.add('hidden');
	}
}
