import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", "container"];

	connect() {
		super.connect();
	}

	toggle() {
		this.containerTarget.classList.toggle('hidden')
		this.buttonTarget.classList.toggle('bg-veryDarkButton');
		this.buttonTarget.classList.toggle('text-white');
	}

	lost_focus() {
		this.containerTarget.classList.add('hidden');
		this.buttonTarget.classList.remove('bg-veryDarkButton', 'text-white');
	}
}
