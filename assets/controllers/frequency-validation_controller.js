import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["mainSelect", "frequencySelect"];

    connect() {
        // On connect, update all frequencies initially
        this.updateAllFrequencies();

        // Attach change event to each main select
        this.mainSelectTargets.forEach(main => {
            main.addEventListener("change", () => {
                // Main select changed
                this.updateFrequencyFor(main);
            });
        });
    }

    updateAllFrequencies() {
        // Updating frequency for all main selects
        this.mainSelectTargets.forEach(main => this.updateFrequencyFor(main));
    }

    updateFrequencyFor(mainSelect) {
        // Get the ID of the main select, e.g. "schedule_DELETE_UNCONFIRMED_USERS_CRON_day_of_week"
        const mainId = mainSelect.id;

        // Build frequency input ID by appending "_frequency"
        const frequencyId = mainId + "_frequency";

        // Find frequency input by ID (using Stimulus frequencySelectTargets array)
        const frequencyInput = this.frequencySelectTargets.find(f => f.id === frequencyId);

        if (!frequencyInput) {
            // No frequency input found for the main field
            return;
        }

        // Get selected options of the main select
        const selectedOptions = Array.from(mainSelect.selectedOptions);

        // Check if the "*" option (All days) is selected
        const hasAll = selectedOptions.some(opt => opt.value === "*");

        if (hasAll) {
            // If '*' is selected — restore full range
            frequencyInput.max = 10;
            frequencyInput.min = 1;

            // If the current value is out of range, reset it
            if (parseInt(frequencyInput.value, 10) > 10) {
                frequencyInput.value = "10";
            }

            frequencyInput.disabled = false;
            return; // Skip further validation logic
        }

        // Normal frequency validation logic when "*" not selected
        const count = selectedOptions.length || 1;

        // The Maximum allowed frequency is (number of selected options - 1) or at least 1
        const maxAllowed = count > 1 ? count - 1 : 1;

        // Set new min and max for the frequency range input
        frequencyInput.max = maxAllowed;
        frequencyInput.min = 1;
        frequencyInput.disabled = false;

        // If the current frequency value is greater than max allowed, reset it
        if (parseInt(frequencyInput.value, 10) > maxAllowed) {
            frequencyInput.value = String(maxAllowed);

            // Trigger input event so any listeners or UI updates react
            frequencyInput.dispatchEvent(new Event("input"));
        }
    }
}
