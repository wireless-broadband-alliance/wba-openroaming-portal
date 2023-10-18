import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
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
