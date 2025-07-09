import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["mainSelect", "frequencySelect"];

    connect() {
        console.log("Stimulus controller connected");
        this.updateAllFrequencyOptions();

        this.mainSelectTargets.forEach(main => {
            main.addEventListener("change", () => {
                console.log(`Main select changed: ${main.name}, selected count: ${main.selectedOptions.length}`);
                this.updateFrequencyFor(main);
            });
        });
    }

    updateAllFrequencyOptions() {
        console.log("Updating frequency options for all main selects");
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
            console.warn(`No frequency select found for main field ${mainId}`);
            return;
        }

        const selectedCount = mainSelect.selectedOptions.length;
        const maxAllowed = (selectedCount > 1) ? selectedCount - 1 : 1;

        console.log(`For main field ${mainId}: selectedCount = ${selectedCount}, maxAllowed frequency = ${maxAllowed}`);

        Array.from(frequencySelect.options).forEach(option => {
            const val = parseInt(option.value, 10);
            const disabled = val > maxAllowed;
            option.disabled = disabled;
            if (disabled) {
                console.log(`Disabling frequency option ${option.value} on ${frequencySelect.id}`);
            } else {
                console.log(`Enabling frequency option ${option.value} on ${frequencySelect.id}`);
            }
        });

        const currentFrequency = parseInt(frequencySelect.value, 10);
        if (currentFrequency > maxAllowed) {
            console.log(`Current frequency ${currentFrequency} is greater than maxAllowed ${maxAllowed}, resetting value`);
            frequencySelect.value = maxAllowed.toString();
            frequencySelect.dispatchEvent(new Event("change"));
        }
    }
}
