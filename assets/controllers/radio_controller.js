import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["on", "off"];

	connect() {
		super.connect();
	}

	toggle(event) {
		if (event.target.value == "ON") {
			this.onTarget.classList.remove('hidden');
			this.offTarget.classList.add('hidden');
		} else {
			this.onTarget.classList.add('hidden');
			this.offTarget.classList.remove('hidden');
		}
	}
}
