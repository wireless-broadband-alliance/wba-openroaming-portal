import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function CardsActions(
			radioButtons,
			textInputs,
			cards
		) {
			if (radioButtons && radioButtons.length > 0) {
				const toggleInputState = () => {
					const isEnabled = radioButtons[0].checked; // Use the first radio button to determine the state

					textInputs.forEach((input) => {
						input.readOnly = !isEnabled;
						input.classList.toggle('cursor-not-allowed', !isEnabled);
					});

					cards.forEach((card) => {
						card.classList.toggle('bg-white', isEnabled);
						card.classList.toggle('bg-disableCardsColor', !isEnabled);
					});
				};

				toggleInputState();
				radioButtons.forEach((radioButton) => {
					radioButton.addEventListener('input', toggleInputState);
				});
			}
		}

		// Capport
		const capportRadioButtons = document.querySelectorAll('[name="capport[CAPPORT_ENABLED]"]');
		const capportTextInputs = [
			document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]'),
			document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]')
		];

		const capportCards = [
			document.getElementById('CAPPORT_PORTAL_URL'),
			document.getElementById('CAPPORT_VENUE_INFO_URL')
		];
		CardsActions(capportRadioButtons, capportTextInputs, capportCards);
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

		// SAML
		const samlTextInputs = [
			document.querySelector('[name="auth[AUTH_METHOD_SAML_LABEL]"]'),
			document.querySelector('[name="auth[AUTH_METHOD_SAML_DESCRIPTION]"]')
		];

		const samlCards = [
			document.getElementById('AUTH_METHOD_SAML_LABEL'),
			document.getElementById('AUTH_METHOD_SAML_DESCRIPTION')
		];

		CardsActions(
			document.querySelector('[name="auth[AUTH_METHOD_SAML_ENABLED]"]'),
			samlTextInputs,
			samlCards
		);

		// Google
		const googleTextInputs = [
			document.querySelector('[name="auth[AUTH_METHOD_GOOGLE_LOGIN_LABEL]"]'),
			document.querySelector('[name="auth[AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION]"]')
		];

		const googleCards = [
			document.getElementById('AUTH_METHOD_GOOGLE_LOGIN_LABEL'),
			document.getElementById('AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION')
		];

		CardsActions(
			document.querySelector('[name="auth[AUTH_METHOD_GOOGLE_LOGIN_ENABLED]"]'),
			googleTextInputs,
			googleCards
		);

		// REGISTER
		const registerTextInputs = [
			document.querySelector('[name="auth[AUTH_METHOD_REGISTER_LABEL]"]'),
			document.querySelector('[name="auth[AUTH_METHOD_REGISTER_DESCRIPTION]"]')
		];

		const registerCards = [
			document.getElementById('AUTH_METHOD_REGISTER_LABEL'),
			document.getElementById('AUTH_METHOD_REGISTER_DESCRIPTION')
		];

		CardsActions(
			document.querySelector('[name="auth[AUTH_METHOD_REGISTER_ENABLED]"]'),
			registerTextInputs,
			registerCards
		);

		// TRADITIONAL LOGIN
		const traditionalLoginTextInputs = [
			document.querySelector('[name="auth[AUTH_METHOD_LOGIN_TRADITIONAL_LABEL]"]'),
			document.querySelector('[name="auth[AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION]"]')
		];

		const traditionalLoginCards = [
			document.getElementById('AUTH_METHOD_LOGIN_TRADITIONAL_LABEL'),
			document.getElementById('AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION')
		];

		CardsActions(
			document.querySelector('[name="auth[AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED]"]'),
			traditionalLoginTextInputs,
			traditionalLoginCards
		);

	}
}
