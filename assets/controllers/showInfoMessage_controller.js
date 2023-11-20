import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Display the Info message
		function showInfoMessage(radioGroupName, messageElementId) {
			const RadioButtons = document.querySelectorAll(`[name="${radioGroupName}"]`);
			const InfoMessage = document.getElementById(messageElementId);

			if (RadioButtons) {
				const toggleMessageState = () => {
					const checkedRadioButton = document.querySelector(`[name="${radioGroupName}"]:checked`);

					if (checkedRadioButton) {
						const EnabledValue = checkedRadioButton.value;

						if (EnabledValue === 'true') {
							InfoMessage.classList.remove('hidden');
						} else {
							InfoMessage.classList.add('hidden');
						}
					}
				};

				// Attach input event listener to all radio buttons
				RadioButtons.forEach(radioButton => {
					radioButton.addEventListener('input', toggleMessageState);
				});

				toggleMessageState(); // Call it initially to handle the default state
			}
		}

		// Call Info Message with the appropriate parameters
		showInfoMessage('capport[CAPPORT_ENABLED]', 'capportMessage');
		showInfoMessage('ldap[SYNC_LDAP_ENABLED]', 'ldapMessage');
	}
}
