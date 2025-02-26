import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        super.connect();
    }

    toggle(event) {
        const selectedOption = event.target.value;

        const options = this.element.querySelectorAll("[data-option-target]");

        options.forEach((option) => {
            if (option.dataset.optionTarget === selectedOption) {
                option.classList.remove("hidden");
            } else {
                option.classList.add("hidden");
            }
        });
    }
}
