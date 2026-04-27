import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toast'];

    connect() {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.toastTarget.style.maxHeight = this.toastTarget.scrollHeight + 'px';
                this.toastTarget.style.opacity = '1';
            });
        });
    }

    close() {
        this.toastTarget.style.maxHeight = '0';
        this.toastTarget.style.opacity = '0';
    }
}