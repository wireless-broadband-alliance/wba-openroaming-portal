import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['agreeTerms', 'confirmationModal', 'button'];

    connect() {
        fetch('/get-terms-status')
            .then((res) => res.json())
            .then((data) => {
                this.updateTermsCheckbox(data.termsAccepted);
                this.toggleSubmitButtons();
            });
    }

    showModal(event) {
        event.preventDefault();
        // Show the modal if the terms checkbox is not checked
        if (!this.agreeTermsTarget.checked) {
            this.confirmationModalTarget.classList.remove('hidden');
        } else {
            // Get href of clicked link
            window.location.href = event.currentTarget.getAttribute('href');
        }
    }

    handleCheckboxChange() {
        const isChecked = this.agreeTermsTarget.checked;

        fetch(isChecked ? '/accept-terms' : '/reject-terms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => {
                console.log(data.message);
            });

        // Show/Hide warning widget based on the checkbox state if the user clicks any of the authentication methods
        if (isChecked) this.closeConfirmationModal();

        this.toggleSubmitButtons();
    }

    updateTermsCheckbox(accepted) {
        if (this.agreeTermsTarget) {
            this.agreeTermsTarget.checked = accepted;
        }
    }

    toggleSubmitButtons() {
        const isChecked = this.agreeTermsTarget?.checked || false;
        for (let button of this.buttonTargets) {
            // btn-disabled (for general buttons)
            button.classList.toggle('btn-disabled', !isChecked);
            if (button.classList.contains('btn-secondary')) {
                // btn-secondary-disabled (for the specific login button)
                button.classList.toggle('btn-secondary-disabled', !isChecked);
            }
        }
    }

    closeConfirmationModal() {
        this.confirmationModalTarget.classList.add('hidden');
    }
}
