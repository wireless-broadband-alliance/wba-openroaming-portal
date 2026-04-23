import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['initialOptions', 'letsEncryptOptions'];

    selectLetsEncrypt(event) {
        event.preventDefault();

        this.initialOptionsTarget.classList.add('hidden');
        this.letsEncryptOptionsTarget.classList.remove('hidden');
    }

    back() {
        this.letsEncryptOptionsTarget.classList.add('hidden');
        this.initialOptionsTarget.classList.remove('hidden');
    }
}
