import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["on", "off"];

    connect() {
        super.connect();
    }

    toggle(event) {
        if (event.target.value == "ON" || event.target.value == "On" || event.target.value == "true" || event.target.value == "Demo" || event.target.value == "LINK") {
            this.onTarget.classList.remove('hidden');
            this.offTarget.classList.add('hidden');
        } else {
            this.onTarget.classList.add('hidden');
            this.offTarget.classList.remove('hidden');
        }
    }
}
