import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["select"];

    connect() {
        this.selectTarget.addEventListener("change", this.updateUrl.bind(this));
    }

    updateUrl(event) {
        const value = event.target.value;

        // Validate that the value is an integer
        if (this.isInteger(value)) {
            const urlParams = new URLSearchParams(window.location.search);

            // Set the 'count' parameter to the selected value
            urlParams.set("count", value);

            // Navigate to the new URL
            window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
        }
    }

    isInteger(value) {
        return Number.isInteger(Number(value));
    }
}
