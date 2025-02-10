import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["button", 'form'];

    connect() {
        super.connect();
    }

    delete_user() {
        const user_uuid = this.buttonTarget.getAttribute('data-user-uuid');
        // Show warning message
        if (confirm(`Are you sure you want to delete the user with UUID ${user_uuid}?`)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    delete_saml() {
        const saml_name = this.buttonTarget.getAttribute('data-provider-name');
        // Show warning message
        if (confirm(`Are you sure you want to delete this SAML Provider "${saml_name}"? All the users associated with this provider will also be eliminated`)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    enable_saml() {
        const providerName = this.buttonTarget.getAttribute('data-provider-name');
        // Show warning message
        if (confirm(`Are you sure you want to enable SAML for provider ID ${providerName}?`)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    revoke_profiles() {
        const user_uuid = this.buttonTarget.getAttribute('data-user-uuid');
        // Show warning message
        if (confirm(`Are you sure you want to revoke all the profiles associated with this account: ${user_uuid}?`)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }

    revoke_saml() {
        const providerName = this.buttonTarget.getAttribute('data-provider-name');
        if (confirm(`Are you sure you want to revoke all profiles associated with the SAML Provider: ${providerName}?`)) {
            // If confirmed, submit the form
            this.formTarget.submit();
        }
    }
}
