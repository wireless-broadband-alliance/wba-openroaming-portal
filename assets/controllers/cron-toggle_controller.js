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
        // Show or hide advanced fields depending on toggle
        this.advancedTargets.forEach(el => {
            el.classList.toggle('hidden', !isAdvanced);
        });

        // Show all frequency and time inputs ONLY if NOT advanced mode
        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.classList.toggle('hidden', isAdvanced);
        });

        // For each group, selectively show day_of_week or day_of_month inputs based on frequency,
        // but ONLY if NOT advanced mode (otherwise hide them)
        const groups = new Set(this.frequencyTargets.map(el => el.closest('[data-cron-toggle-group]').dataset.cronToggleGroup));

        groups.forEach(group => {
            const freqEl = this.frequencyTargets.find(el => el.closest('[data-cron-toggle-group]').dataset.cronToggleGroup === group);
            const frequency = freqEl ? freqEl.value : null;
            const freqNormalized = frequency ? frequency.toLowerCase() : '';

            this.day_of_weekTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    // Show day_of_week only if NOT advanced AND frequency is 'weekly'
                    if (!isAdvanced && freqNormalized === 'weekly') {
                        el.classList.remove('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }
            });

            this.day_of_monthTargets.forEach(el => {
                if (el.dataset.cronToggleGroup === group) {
                    // Show day_of_month only if NOT advanced AND frequency is 'monthly'
                    if (!isAdvanced && freqNormalized === 'monthly') {
                        el.classList.remove('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }
            });
        });
    }
}
