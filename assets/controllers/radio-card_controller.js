import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["on", "card", "input", "link", "linkInput", "textEditorInput"];

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
		this.linkInputTarget.classList.remove("hidden");
		this.textEditorInputTarget.classList.add("hidden");

	}

	showTextEditor() {
		console.log('show fancy input');
		this.linkInputTarget.classList.add("hidden");
		this.textEditorInputTarget.classList.remove("hidden");
	}
}
