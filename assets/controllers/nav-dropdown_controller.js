import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'container'];
    static values = {toggleSelected: {type: Boolean, default: true}};

    toggle() {
        this.containerTarget.classList.toggle('hidden');

        if (this.toggleSelectedValue) {
            this.buttonTarget.classList.toggle('selected');
        }
    }

    lost_focus() {
        if (!this.containerTarget.matches(':hover')) {
            this.containerTarget.classList.add('hidden');

            if (this.toggleSelectedValue) {
                this.buttonTarget.classList.remove('selected');
            }
        }

        toggleHeader()
        {
            this.containerTarget.classList.toggle('hidden');
        }
    }
}
