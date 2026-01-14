import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'form'];

    connect() {
        super.connect();
    }

    delete_user() {
        const message = this.buttonTarget.getAttribute('data-user-actions-confirm-delete');
        // Show warning message
        if (confirm(message)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    revoke_profiles() {
        const message = this.buttonTarget.getAttribute('data-user-actions-confirm-revoke');
        // Show warning message
        if (confirm(message)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    deleteDomainSource() {
        // Read the confirmation message from the button
        const message = this.buttonTarget.getAttribute('data-domain-confirm-delete');

        // Show warning message
        if (confirm(message)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }
}
