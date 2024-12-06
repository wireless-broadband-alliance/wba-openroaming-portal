import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["on", "card", "input", "link", "textInput", "fancyInput"];

	connect() {
		super.connect();

		if (this.onTarget.checked) {
			this.unblock();
		} else {
			this.block();
		}

		if(this.linkTarget.checked) {
			this.showLink();
		} else {
			this.showTextEditor();
		}
	}

	block() {
		for (let t of this.inputTargets) {
			t.readOnly = true;
			t.classList.add('cursor-not-allowed');
		}

		for (let t of this.cardTargets) {
			t.classList.remove("bg-white");
			t.classList.add("bg-disableCardsColor");
		}
	}

	unblock() {
		for (let t of this.inputTargets) {
			t.readOnly = false;
			t.classList.remove('cursor-not-allowed');
		}

		for (let t of this.cardTargets) {
			t.classList.add("bg-white");
			t.classList.remove("bg-disableCardsColor");
		}
	}

	showLink() {
		console.log('show link input');
		this.textInputTarget.classList.remove("hidden");
		this.fancyInputTarget.classList.add("hidden");

	}

	showTextEditor() {
		console.log('show fancy input');
		this.textInputTarget.classList.add("hidden");
		this.fancyInputTarget.classList.remove("hidden");
	}
}
