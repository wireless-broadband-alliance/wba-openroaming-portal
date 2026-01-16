import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'container'];
    static values = { toggleSelected: { type: Boolean, default: true } };

    // Toggle the container visibility and button selected state
    toggle() {
        this.containerTarget.classList.toggle('hidden');

        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.toggle('selected');
        }
    }

    // Hide container when losing focus, if not hovered
    lostFocus() {
        if (!this.containerTarget.matches(':hover')) {
            this.containerTarget.classList.add('hidden');

            if (this.toggleSelectedValue) {
                this.buttonTarget.classList.remove('selected');
            }
        }
    }

    // Example of a toggleHeader method, if you need it
    toggleHeader() {
        this.containerTarget.classList.toggle('hidden');

        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.toggle('selected');
        }
    }
}
