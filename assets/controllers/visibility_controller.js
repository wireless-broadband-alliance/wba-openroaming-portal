import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["first", "second"];
	static values = {
		isOpened: Boolean
	};

	connect() {
		super.connect();

		if (this.hasFirstTarget) {
			console.log('%c Visibility - Detected for ' + this.firstTarget.name, 'background: green; color: black');
		}

		if (this.hasSecondTarget) {
			console.log('%c Visibility - Detected for ' + this.secondTarget.name, 'background: green; color: black');
		}
	}

	toggle() {
		this.isOpenedValue = !this.isOpenedValue;

		if (this.isOpenedValue) {
			this.firstTarget.classList.remove('hidden');
			this.secondTarget.classList.add('hidden');
		} else {
			this.firstTarget.classList.add('hidden');
			this.secondTarget.classList.remove('hidden');
		}
	}

}
