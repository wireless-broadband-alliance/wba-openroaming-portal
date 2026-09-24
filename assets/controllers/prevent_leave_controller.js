import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.isSafeLeave = false;

        // Bind the handler function so it can be properly removed on disconnect
        this.onBeforeUnload = this.handleBeforeUnload.bind(this);
        window.addEventListener('beforeunload', this.onBeforeUnload);
    }

    disconnect() {
        // Remove listener to prevent memory leaks or unexpected behavior during Turbo navigation
        window.removeEventListener('beforeunload', this.onBeforeUnload);
    }

    // Action invoked by allowed trigger elements (e.g., Download, Done)
    allowLeave() {
        this.isSafeLeave = true;
    }

    handleBeforeUnload(event) {
        if (!this.isSafeLeave) {
            // Modern browsers require both preventDefault and setting returnValue
            event.preventDefault();
            event.returnValue = '';

            return '';
        }
    }
}