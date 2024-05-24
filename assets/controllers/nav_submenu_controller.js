import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", "container"];

	connect() {
		super.connect();
	}

	toggle() {
		console.log(this.containerTarget);
		this.containerTarget.classList.toggle('hidden');
	}
}
