import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'advanced', 'frequency', 'time', 'day_of_week', 'day_of_month'];

    connect() {
        this.updateVisibility();

        this.frequencyTargets.forEach(el => {
            el.addEventListener('change', () => this.updateVisibility());
        });
    }

    toggleChanged() {
        this.updateVisibility();
    }

    updateVisibility() {
        const isAdvanced = this.toggleTarget.checked;

        // Show/hide advanced fields
        this.advancedTargets.forEach(el => {
            el.closest('.form-group')?.classList.toggle('hidden', !isAdvanced);
        });

        // Show/hide frequency and time fields when not in advanced mode
        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.closest('.form-group')?.classList.toggle('hidden', isAdvanced);
        });

        // Extract unique groups from frequency targets
        const groups = new Set(
            this.frequencyTargets
                .map(el => el.dataset.cronToggleGroup)
                .filter(Boolean)
        );

        groups.forEach(group => {
            // Get frequency input element for this group
            const freqEl = this.frequencyTargets.find(el =>
                el.dataset.cronToggleGroup === group
            );

            const frequency = freqEl?.value?.trim().toLowerCase() || '';

            // day_of_week logic (show if frequency is weekly)
            this.day_of_weekTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    el.closest('.form-group')?.classList.toggle('hidden', !(frequency === 'weekly' && !isAdvanced));
                }
            });

            // day_of_month logic (show if frequency is monthly)
            this.day_of_monthTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    el.closest('.form-group')?.classList.toggle('hidden', !(frequency === 'monthly' && !isAdvanced));
                }
            });
        });
    }
}
