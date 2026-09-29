import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // Add 'select' and 'item' targets
    static targets = ['first', 'second', 'select', 'item'];

    connect() {
        // Only run update on connect if a select target actually exists
        if (this.hasSelectTarget) {
            this.update();
        }
    }

    update() {
        // Guard clause in case update() is triggered manually without a select target
        if (!this.hasSelectTarget) return;

        const value = this.selectTarget.value;

        if (this.hasItemTargets) {
            this.itemTargets.forEach((element) => {
                const expectedValue = element.dataset.visibilityValue;

                if (expectedValue === value) {
                    element.classList.remove('hidden');
                } else {
                    element.classList.add('hidden');
                }
            });
        }
    }

    toggle() {
        if (this.hasFirstTarget && this.hasSecondTarget) {
            this.firstTarget.classList.toggle('hidden');
            this.secondTarget.classList.toggle('hidden');
            return;
        }
        if (this.hasFirstTarget) {
            this.firstTarget.classList.toggle('hidden');
        }

        if (this.hasSecondTarget) {
            this.secondTarget.classList.toggle('hidden');
        }
    }
}
