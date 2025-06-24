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

        // Show advanced fields if advanced mode, else hide
        this.advancedTargets.forEach(el => {
            el.classList.toggle('hidden', !isAdvanced);
        });

        // Show simple fields if NOT advanced mode, else hide
        [...this.frequencyTargets, ...this.timeTargets].forEach(el => {
            el.classList.toggle('hidden', isAdvanced);
        });
    }
}
