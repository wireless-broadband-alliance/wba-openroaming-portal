import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "toggle",
        "advanced",
        "day_of_week",
        "day_of_month",
        "months_of_the_year",
        "time",
        "day_of_week_frequency",
        "day_of_month_frequency",
        "months_of_the_year_frequency",
    ];

    connect() {
        this.updateVisibility();

        // Listen for toggle change
        this.toggleTarget.addEventListener("change", () => this.updateVisibility());
    }

    toggleChanged() {
        this.updateVisibility();
    }

    updateVisibility() {
        const isAdvanced = this.toggleTarget.checked;

        const toggleGroupElements = (targets, show) => {
            targets.forEach((el) => {
                el.closest(".form-group")?.classList.toggle("hidden", !show);
            });
        };

        // Advanced field shown when advanced mode is on
        toggleGroupElements(this.advancedTargets, isAdvanced);

        // All others are shown only when advanced is off
        toggleGroupElements(this.day_of_weekTargets, !isAdvanced);
        toggleGroupElements(this.day_of_monthTargets, !isAdvanced);
        toggleGroupElements(this.months_of_the_yearTargets, !isAdvanced);
        toggleGroupElements(this.timeTargets, !isAdvanced);

        // Show/hide new frequency fields alongside their related fields
        toggleGroupElements(this.day_of_week_frequencyTargets, !isAdvanced);
        toggleGroupElements(this.day_of_month_frequencyTargets, !isAdvanced);
        toggleGroupElements(this.months_of_the_year_frequencyTargets, !isAdvanced);

        this.toggleGroupTitlesAndIcons(isAdvanced);
    }

    toggleGroupTitlesAndIcons() {
        const fieldTypes = [
            "advanced",
            "day_of_week",
            "day_of_month",
            "months_of_the_year",
            "time",
            "day_of_week_frequency",
            "day_of_month_frequency",
            "months_of_the_year_frequency",
        ];

        const allTargets = fieldTypes.flatMap((type) => this[`${type}Targets`] || []);
        const groupNames = [...new Set(allTargets.map((el) => el.dataset.cronToggleGroup).filter(Boolean))];

        groupNames.forEach((group) => {
            fieldTypes.forEach((type) => {
                const inputs = this[`${type}Targets`].filter((el) => el.dataset.cronToggleGroup === group);
                const anyVisible = inputs.some((el) => !el.closest(".form-group")?.classList.contains("hidden"));

                this.toggleTitleAndIcon(group, type, anyVisible);
            });
        });
    }

    toggleTitleAndIcon(group, type, show) {
        const groupEl = document.getElementById(group);
        if (!groupEl) return;

        const titleEl = groupEl.querySelector(`[data-cron-toggle-title="${type}"]`);
        if (!titleEl) return;

        const parentRow = titleEl.closest(".flex");
        if (!parentRow) return;

        const iconEl = parentRow.querySelector("img");
        titleEl.classList.toggle("hidden", !show);
        if (iconEl) {
            iconEl.classList.toggle("hidden", !show);
        }
    }
}
