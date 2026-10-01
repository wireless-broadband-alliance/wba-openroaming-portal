import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.boundClose = this.closeIfOutside.bind(this);
        document.addEventListener('click', this.boundClose);
    }

    disconnect() {
        document.removeEventListener('click', this.boundClose);
    }

    closeIfOutside(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.element.open = false;
        }
    }
}
