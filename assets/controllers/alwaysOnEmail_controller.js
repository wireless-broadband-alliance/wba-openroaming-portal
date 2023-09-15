import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const platformMode = document.getElementById("setting_PLATFORM_MODE");
		const emailVerification = document.getElementById("setting_EMAIL_VERIFICATION");

		// Initial update based on setting_PLATFORM_MODE value
		this.updateForm(platformMode, emailVerification);

		// Add an event listener to setting_PLATFORM_MODE input to check the forms in real time
		platformMode.addEventListener("change", () => {
			this.updateForm(platformMode, emailVerification);
		});
	}

	updateForm(platformMode, emailVerification) {
		if (platformMode.value === 'Live') {
			emailVerification.value = 'ON';
			emailVerification.disabled = true;
			emailVerification.classList.add("cursor-not-allowed");
		} else {
			emailVerification.disabled = false;
			emailVerification.classList.remove('cursor-not-allowed');
		}
	}
}
