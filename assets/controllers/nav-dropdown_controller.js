import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'container', 'input', 'display'];
    static values = { toggleSelected: { type: Boolean, default: true } };

    toggle() {
        this.containerTarget.classList.toggle('hidden');

        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.toggle('selected');
        }
    }

    lostFocus() {
        if (!this.containerTarget.matches(':hover')) {
            this.containerTarget.classList.add('hidden');

            if (this.toggleSelectedValue) {
                this.buttonTarget.classList.remove('selected');
            }
        }
    }

    toggleHeader() {
        this.containerTarget.classList.toggle('hidden');

        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.toggle('selected');
        }
    }

    // Called from each <li>'s data-action, reads the chosen value,
    // label, and icon class from data-*-param attributes.
    select(event) {
        const { value, label, iconClass } = event.params;

        this.inputTarget.value = value;
        // must be "change", not "input" — the form's data-model is on(change)|*
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));

        this.displayTarget.innerHTML = iconClass
            ? `<span class="${iconClass} fis rounded-full w-4 h-4 mr-2 shrink-0 bg-gray-200"></span><span class="truncate font-medium text-gray-900">${label}</span>`
            : `<span class="truncate font-medium text-gray-900">${label}</span>`;

        this.containerTarget.classList.add('hidden');
        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.remove('selected');
        }
    }
}
