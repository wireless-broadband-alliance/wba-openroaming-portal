import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function CardsActions(
			selectInput,
			textInputs,
			cards
		) {
			if (selectInput) {
				const toggleInputState = () => {
					const isEnabled = selectInput.value === 'true';

					textInputs.forEach((input) => {
						input.disabled = !isEnabled;
						input.classList.toggle('cursor-not-allowed', !isEnabled);
					});

					cards.forEach((card) => {
						card.classList.toggle('bg-white', isEnabled);
						card.classList.toggle('bg-disableCardsColor', !isEnabled);
					});
				};

				toggleInputState();
				selectInput.addEventListener('input', toggleInputState);
			}
		}

		const ldapTextInputs = [];
		const ldapCards = [];

		// Iterate over LDAP settings to populate the arrays
		const ldapSettings = ['SYNC_LDAP_SERVER', 'SYNC_LDAP_BIND_USER_DN', 'SYNC_LDAP_BIND_USER_PASSWORD', 'SYNC_LDAP_SEARCH_BASE_DN', 'SYNC_LDAP_SEARCH_FILTER'];

		ldapSettings.forEach(settingName => {
			const textInput = document.querySelector(`[name="ldap[${settingName}]"]`);
			const card = document.getElementById(settingName);

			if (textInput && card) {
				ldapTextInputs.push(textInput);
				ldapCards.push(card);
			}
		});

		CardsActions(
			document.querySelector('[name="ldap[SYNC_LDAP_ENABLED]"]'),
			ldapTextInputs,
			ldapCards
		);


		const capportTextInputs = [
			document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]'),
			document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]')
		];

		const capportCards = [
			document.getElementById('CAPPORT_PORTAL_URL'),
			document.getElementById('CAPPORT_VENUE_INFO_URL')
		];
		CardsActions(
			document.querySelector('[name="capport[CAPPORT_ENABLED]"]'),
			capportTextInputs,
			capportCards
		);
	}
}
