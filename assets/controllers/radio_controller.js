import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["on", "off"];

    connect() {
        super.connect();
    }

    toggle(event) {
        const validValues = ["ON", "On", "true", "Demo", "LINK"];

        // Check if the event target's
        if (validValues.includes(event.target.value)) {
            this.onTarget.classList.remove("hidden");
            this.offTarget.classList.add("hidden");
        } else {
            this.onTarget.classList.add("hidden");
            this.offTarget.classList.remove("hidden");
        }
    }
}
