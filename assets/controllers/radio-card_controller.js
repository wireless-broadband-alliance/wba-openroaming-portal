import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["on", "card", "input"];

	connect() {
		super.connect();

		if (this.onTarget.checked) {
			this.unblock();
		} else {
			this.block();
		}
	}

	block() {
		for (let t of this.inputTargets) {
			t.readOnly = true; // This is readOnly because of the user verification card on the status page
			t.classList.add('cursor-not-allowed');
		}

		for (let t of this.cardTargets) {
			t.classList.remove("bg-white");
			t.classList.add("bg-disableCardsColor");
		}
	}

	unblock() {
		for (let t of this.inputTargets) {
			t.readOnly = false; // This is readOnly because of the user verification card on the status page
			t.classList.remove('cursor-not-allowed');
		}

		for (let t of this.cardTargets) {
			t.classList.add("bg-white");
			t.classList.remove("bg-disableCardsColor");
		}
	}
}
