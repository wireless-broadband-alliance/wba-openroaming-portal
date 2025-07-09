import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["mainSelect", "frequencySelect"];

    connect() {
        this.updateAllFrequencyOptions();

        this.mainSelectTargets.forEach(main => {
            main.addEventListener("change", () => {
                // Main select changed
                this.updateFrequencyFor(main);
            });
        });
    }

    updateAllFrequencyOptions() {
        // Updating frequency options for all main selects
        this.mainSelectTargets.forEach(main => this.updateFrequencyFor(main));
    }

    updateFrequencyFor(mainSelect) {
        // Get the ID of the main select, e.g. "schedule_DELETE_UNCONFIRMED_USERS_CRON_day_of_week"
        const mainId = mainSelect.id;

        // Build frequency select ID by appending "_frequency"
        const frequencyId = mainId + "_frequency";

        // Find frequency select by ID (using Stimulus frequencySelectTargets array)
        const frequencySelect = this.frequencySelectTargets.find(f => f.id === frequencyId);

        if (!frequencySelect) {
            // No frequencies selected found for the main field
            return;
        }

        // Check if the "*" option (All days) is selected
        const allSelected = Array.from(mainSelect.selectedOptions).some(opt => opt.value === "*");

        if (allSelected) {
            // Ignore frequency logic - enable all options
            Array.from(frequencySelect.options).forEach(option => {
                option.disabled = false;
            });
            return; // Skip frequency validation logic
        }

        // Normal frequency validation logic when "*" not selected
        const selectedCount = mainSelect.selectedOptions.length;
        const maxAllowed = (selectedCount > 1) ? selectedCount - 1 : 1;

        Array.from(frequencySelect.options).forEach(option => {
            const val = parseInt(option.value, 10);
            const disabled = val > maxAllowed;
            option.disabled = disabled;
        });

        const currentFrequency = parseInt(frequencySelect.value, 10);
        if (currentFrequency > maxAllowed) {
            frequencySelect.value = maxAllowed.toString();
            frequencySelect.dispatchEvent(new Event("change"));
        }
    }
}
