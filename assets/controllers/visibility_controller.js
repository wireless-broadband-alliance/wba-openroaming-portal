import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['first', 'second'];

    connect() {
        this.update();
    }

    update() {
        const value = this.selectTarget.value;

        this.itemTargets.forEach((element) => {
            const expectedValue = element.dataset.visibilityValue;

            if (expectedValue === value) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        });
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
