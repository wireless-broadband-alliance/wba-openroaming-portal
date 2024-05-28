import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ["button", "input"];

	initial_values = [];

	connect() {
		const button = this.buttonTarget;

		// Store the initial values each input
		for (let i of this.inputTargets) {
			this.initial_values[i.id] = i.value;
		}
	}

	input_changed(event){
		if (event.target.value !== this.initial_values[event.target.id]) {
			this.buttonTarget.removeAttribute("disabled");
			this.buttonTarget.classList.remove("cursor-not-allowed");
		} else {
			this.buttonTarget.setAttribute("disabled", "disabled");
			this.buttonTarget.classList.add("cursor-not-allowed");
		}
	}
}
