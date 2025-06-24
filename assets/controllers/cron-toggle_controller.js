import { Controller } from '@hotwired/stimulus';

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

        // Show advanced fields if advanced mode, else hide
        this.advancedTargets.forEach(el => {
            el.classList.toggle('hidden', !isAdvanced);
        });

        // Show simple fields if NOT advanced mode, else hide
        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.classList.toggle('hidden', isAdvanced);
        });

        // Show/hide day_of_week and day_of_month based on frequency and advanced mode
        this.frequencyTargets.forEach(freqEl => {
            const frequency = freqEl.value;
            const group = freqEl.closest('[data-cron-toggle-group]').dataset.cronToggleGroup;

            this.day_of_weekTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    el.classList.toggle('hidden', isAdvanced || frequency !== 'weekly');
                }
            });

            this.day_of_monthTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    el.classList.toggle('hidden', isAdvanced || frequency !== 'monthly');
                }
            });
        });
    }
}
