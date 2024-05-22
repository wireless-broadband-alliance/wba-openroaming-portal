import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["toast"];

	connect() {
		super.connect();

		if (this.hasToastTarget) {
			console.log('%c Visibility - Detected for ' + this.toastTarget.name, 'background: green; color: black');
		}
	}

	close() {
		this.toastTarget.classList.add('hidden');
	}

}
