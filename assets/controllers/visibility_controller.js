import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'item'];

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
}
