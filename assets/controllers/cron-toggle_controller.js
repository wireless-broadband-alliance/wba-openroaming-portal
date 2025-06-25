import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "toggle", "advanced", "frequency", "time",
        "day_of_week", "day_of_month", "interval", "startDate", "endDate"
    ];

    connect() {
        this.updateVisibility();

        // Watch for changes to the toggle and frequency fields
        [...this.frequencyTargets, this.toggleTarget].forEach(el => {
            el.addEventListener("change", () => this.updateVisibility());
        });
    }

    toggleChanged() {
        this.updateVisibility();
    }

    updateVisibility() {
        const isAdvanced = this.toggleTarget.checked;

        const toggleGroupElements = (targets, show) => {
            targets.forEach(el => {
                el.closest(".form-group")?.classList.toggle("hidden", !show);
            });
        };

        // Show only the advanced input field in advanced mode
        toggleGroupElements(this.advancedTargets, isAdvanced);

        // Hide all others when in advanced mode
        toggleGroupElements(this.frequencyTargets, !isAdvanced);
        toggleGroupElements(this.timeTargets, !isAdvanced);
        toggleGroupElements(this.intervalTargets, !isAdvanced);
        toggleGroupElements(this.startDateTargets, !isAdvanced);
        toggleGroupElements(this.endDateTargets, !isAdvanced);
        toggleGroupElements(this.day_of_weekTargets, !isAdvanced);
        toggleGroupElements(this.day_of_monthTargets, !isAdvanced);

        // Now update the titles per field group
        const groups = new Set(this.frequencyTargets.map(el => el.dataset.cronToggleGroup).filter(Boolean));

        groups.forEach(group => {
            const freqEl = this.frequencyTargets.find(el => el.dataset.cronToggleGroup === group);
            const frequency = freqEl?.value?.trim().toLowerCase() || "";

            this.toggleTitles(group, isAdvanced, frequency);
        });
    }

    toggleTitles(group, isAdvanced, frequency) {
        const fieldTypes = [
            "advanced", "frequency", "time",
            "day_of_week", "day_of_month", "interval", "startDate", "endDate"
        ];

        fieldTypes.forEach(type => {
            const inputs = this[`${type}Targets`].filter(el => el.dataset.cronToggleGroup === group);
            const anyVisible = inputs.some(el => !el.closest(".form-group")?.classList.contains("hidden"));

            // Show title only if at least one field of this type is visible
            this.toggleTitleVisibility(group, type, anyVisible);
        });
    }

    toggleTitleVisibility(group, type, show) {
        const groupEl = document.getElementById(group);
        if (!groupEl) return;

        const titleEl = groupEl.querySelector(`[data-cron-toggle-title="${type}"]`);
        if (titleEl) {
            titleEl.classList.toggle("hidden", !show);
        }
    }
}
