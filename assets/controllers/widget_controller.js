import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal'];
    static values = {
        hasErrors: Boolean,
    };

    connect() {
        // Force open modal if validation errors are passed from Symfony
        if (this.hasHasErrorsValue && this.hasErrorsValue) {
            this.open();
            return;
        }

        this.toggleInitialVisibility();
    }

    toggle(event) {
        const isChecked = event.currentTarget.checked;

        if (isChecked) {
            this.open();
        } else {
            this.close();
        }
    }

    toggleInitialVisibility() {
        // Ensure the state is correct on page load
        const checkbox = this.element.querySelector('input[type="checkbox"]');
        if (!checkbox) {
            return;
        }

        if (checkbox.checked) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.remove('hidden');
        }
    }

    close() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.add('hidden');
        }
    }
}
