import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["button", "form"];

    connect() {
        console.log('Revoke Profiles controller connected'); // Optional debug warning
    }

    revoke_profiles(event) {
        event.preventDefault();  // Prevent the default form submission

        const user_uuid = this.buttonTarget.getAttribute('data-user-uuid');

        if (confirm(`Are you sure you want to revoke all the profiles associated with this account: ${user_uuid}?`)) {
            this.formTarget.submit();
        }
    }
}