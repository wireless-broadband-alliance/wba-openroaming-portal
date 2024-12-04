import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["link", "on", "card", "input"];

	connect() {
		super.connect();

		if (this.onTarget.checked) {
			this.unblock();
		} else {
			this.block();
		}

        if (this.linkTarget.checked) {
            this.addCard(//add text input);
            this.removeCard(//remove google docs input);
        } else {
            this.addCard(//add google docs input);
            this.removeCard(//remove text input);
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

    addCard(){
        for (let t of this.cardTargets) {
            // style logic to add a card property
        }
    }

    removeCard(){
        for (let t of this.cardTargets) {
            // style logic to remove a card hidden property
        }
    }

    showContainer(container) {
        container.classList.remove("hidden");
    }

    hideContainer(container) {
        container.classList.add("hidden");
    }
}
