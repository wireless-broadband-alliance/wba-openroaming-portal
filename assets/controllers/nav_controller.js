import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", "container", "drodown", "hamburguer"];

	connect() {
		const alertElement = document.getElementById('alert-2');
		if (alertElement) {
			alertElement.addEventListener('animationend', () => {
				alertElement.classList.add('hidden');
			});
		}

		function remove_element(elementId) {
			setTimeout(function () {
				let errorContainer = document.getElementById(elementId);
				if (errorContainer) {
					errorContainer.style.transition = 'opacity 0.5s';
					errorContainer.style.opacity = '0';
					setTimeout(function () {
						errorContainer.remove();
					}, 500); // Wait for the transition to finish and then remove the element
				}
			}, 5000); // set a time of five seconds and then remove the error
		}

		remove_element('errorDisplay');
		remove_element('successDisplay');
	}

	toggle() {
		this.buttonTarget.classList.toggle('open');

		this.containerTarget.classList.toggle('hidden');

		// What is this used for?
		const bodyElement = document.body;
		if (this.containerTarget.classList.contains('hidden')) {
			bodyElement.classList.remove('overflow-hidden');
		} else {
			bodyElement.classList.add('overflow-hidden');
		}
	}
}
