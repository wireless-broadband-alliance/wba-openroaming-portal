import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'advanced', 'frequency', 'time'];

    connect() {
        console.log('[cron-toggle] connected');
        this.updateVisibility();
    }

    toggleChanged() {
        console.log('[cron-toggle] toggleChanged triggered');
        this.updateVisibility();
    }

    updateVisibility() {
        const isAdvanced = this.toggleTarget.checked;
        console.log(`[cron-toggle] isAdvanced: ${isAdvanced}`);

        this.advancedTargets.forEach(el => {
            console.log('[cron-toggle] advancedTarget:', el);
            const group = el.closest('.form-group');
            console.log('[cron-toggle] .form-group of advancedTarget:', group);
            group?.classList.toggle('hidden', !isAdvanced);
        });

        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            console.log('[cron-toggle] frequency/time Target:', el);
            const group = el.closest('.form-group');
            console.log('[cron-toggle] .form-group of freq/time Target:', group);
            group?.classList.toggle('hidden', isAdvanced);
        });
    }
}
