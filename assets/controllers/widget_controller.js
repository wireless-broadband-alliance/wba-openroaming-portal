import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function showWidgets(buttonId, menuId) {
			const widgetButton = document.getElementById(buttonId);
			const widgetItem = document.getElementById(menuId);

			widgetButton.addEventListener('click', () => {
				// Toggle the display of the widgetItem
				if (widgetItem.style.display === 'block') {
					widgetItem.style.display = 'none';
					widgetButton.classList.remove('focus:bg-[#7DB928]', 'focus:text-white', 'focus:animate-spin-slow');
				} else {
					widgetItem.style.display = 'block';
					widgetButton.classList.add('focus:bg-[#7DB928]', 'focus:text-white', 'focus:animate-spin-slow');
				}
			});

			// Close the widget when clicking outside it
			document.addEventListener('click', (event) => {
				// Check if the click is outside the button and the widgetItem
				if (!widgetButton.contains(event.target) && !widgetItem.contains(event.target)) {
					widgetItem.style.display = 'none';
				}
			});
		}

		showWidgets('widgetUserButton', 'widgetUser');

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
}
