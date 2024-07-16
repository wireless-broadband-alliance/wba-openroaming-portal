import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", "container"];

	connect() {
		super.connect();
	}

	toggle() {
		this.containerTarget.classList.toggle('hidden');
		this.buttonTarget.classList.toggle('selected');
	}

	lost_focus() {
		if (!this.containerTarget.matches(":hover")) {
			this.containerTarget.classList.add('hidden');
			this.buttonTarget.classList.remove('selected');
		}
	}
}
