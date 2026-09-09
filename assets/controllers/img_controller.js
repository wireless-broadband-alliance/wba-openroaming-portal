import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['preview', 'input', 'removeFlag', 'filledState', 'emptyState'];

    update() {
        const file = this.inputTarget.files[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            this.previewTarget.src = e.target.result;
            this.removeFlagTarget.checked = false;
            this.filledStateTarget.classList.remove('hidden');
            this.emptyStateTarget.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    remove(event) {
        event.preventDefault();
        this.inputTarget.value = '';
        this.previewTarget.src = '';
        this.removeFlagTarget.checked = true;
        this.filledStateTarget.classList.add('hidden');
        this.emptyStateTarget.classList.remove('hidden');
    }
}
