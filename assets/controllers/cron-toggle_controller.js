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

        // Toggle advanced fields visibility
        this.advancedTargets.forEach(el => {
            el.classList.toggle('hidden', !isAdvanced);
        });

        // Toggle frequency and time visibility
        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.classList.toggle('hidden', isAdvanced);
        });

        // Get unique groups
        const groups = new Set(this.frequencyTargets.map(el => el.closest('[data-cron-toggle-group]').dataset.cronToggleGroup));

        groups.forEach(group => {
            // Find the frequency input for this group
            const freqEl = this.frequencyTargets.find(el => el.closest('[data-cron-toggle-group]').dataset.cronToggleGroup === group);
            console.log('freqEl for group', group, freqEl);
            // Get the frequency value (string), lowercase and trim it
            const frequency = freqEl && freqEl.value ? freqEl.value.trim().toLowerCase() : '';

            // For day_of_week fields in this group:
            this.day_of_weekTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    if (!isAdvanced && frequency === 'weekly') {
                        el.classList.remove('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }
            });

            // For day_of_month fields in this group:
            this.day_of_monthTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    if (!isAdvanced && frequency === 'monthly') {
                        el.classList.remove('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }
            });
        });
    }
}
