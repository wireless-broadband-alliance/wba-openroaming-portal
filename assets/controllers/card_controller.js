import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'info'];

    show_info() {
        this.infoTarget.style.maxHeight = this.infoTarget.scrollHeight + 'px';
    }

    hide_info() {
        this.infoTarget.style.maxHeight = '0';
    }
}
