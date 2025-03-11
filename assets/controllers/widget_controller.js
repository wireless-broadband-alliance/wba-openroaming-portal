import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["modal"];

    connect() {
        super.connect();
        this.toggleInitialVisibility();
    }

    toggle(event) {
        // Check the state of the checkbox
        const isChecked = event.currentTarget.checked;

        if (isChecked) {
            this.open();
        } else {
            this.close();
        }
    }

    toggleInitialVisibility() {
        // Ensure the state is correct on page load
        const checkbox = this.element.querySelector('input[type="checkbox"]');
        if (!checkbox) {
            return;
        }

        console.log("Toggle Initial Visibility - Checkbox checked state:", checkbox.checked);
        if (checkbox.checked) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.modalTarget.classList.remove("hidden");
    }

    close() {
        this.modalTarget.classList.add("hidden");
    }
}
