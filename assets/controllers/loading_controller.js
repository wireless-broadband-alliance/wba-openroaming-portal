import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["button", "overlay"];

    show(event) {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
        }

        // Show overlay
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.remove('hidden');
        }
    }
}
