import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'advanced', 'frequency', 'time'];

    connect() {
        this.updateVisibility();
    }

    toggleChanged() {
        this.updateVisibility();
    }

    updateVisibility() {
        const isAdvanced = this.toggleTarget.checked;

        this.advancedTargets.forEach(el => {
            el.closest('.form-group')?.classList.toggle('hidden', !isAdvanced);
        });

        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.closest('.form-group')?.classList.toggle('hidden', isAdvanced);
        });
    }
}
