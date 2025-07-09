import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["mainSelect", "frequencySelect"];

    connect() {
        this.updateFrequencyOptions();
        this.mainSelectTargets.forEach(main => {
            main.addEventListener("change", () => this.updateFrequencyOptions());
        });
    }

    updateFrequencyOptions() {
        this.mainSelectTargets.forEach((mainSelect, index) => {
            const frequencySelect = this.frequencySelectTargets[index];
            if (!frequencySelect) return;

            const selectedCount = mainSelect.selectedOptions.length;
            const maxAllowed = selectedCount > 1 ? selectedCount - 1 : 1;

            Array.from(frequencySelect.options).forEach(option => {
                const val = parseInt(option.value, 10);
                option.disabled = val > maxAllowed;
            });

            const freqVal = parseInt(frequencySelect.value, 10);
            if (freqVal > maxAllowed) {
                frequencySelect.value = maxAllowed.toString();
                frequencySelect.dispatchEvent(new Event("change"));
            }
        });
    }
}
