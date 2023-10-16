import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function DisableEnableCards(
			selectInput,
			firstInput,
			secondInput,
			firstCard,
			secondCard,
		) {
			if (selectInput) {
				const toggleInputState = () => {
					const capportEnabledValue = selectInput.value;

					if (capportEnabledValue === 'true') {
						firstInput.disabled = false;
						secondInput.disabled = false;

						firstInput.classList.remove('cursor-not-allowed');
						secondInput.classList.remove('cursor-not-allowed');
						firstCard.classList.add('bg-white');
						secondCard.classList.add('bg-white');
						firstCard.classList.remove('bg-disableCardsColor');
						secondCard.classList.remove('bg-disableCardsColor');
					} else {
						firstInput.disabled = true;
						secondInput.disabled = true;

						firstInput.classList.add('cursor-not-allowed');
						secondInput.classList.add('cursor-not-allowed');
						firstCard.classList.remove('bg-white');
						secondCard.classList.remove('bg-white');
						firstCard.classList.add('bg-disableCardsColor');
						secondCard.classList.add('bg-disableCardsColor');
					}
				};

				toggleInputState();
				selectInput.addEventListener('input', toggleInputState);
			}
		}

		// Call the functions
		DisableEnableCards(
			document.querySelector('[name="capport[CAPPORT_ENABLED]"]'),
			document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]'),
			document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]'),
			document.getElementById('CAPPORT_PORTAL_URL'),
			document.getElementById('CAPPORT_VENUE_INFO_URL'),
		);
	}
}
