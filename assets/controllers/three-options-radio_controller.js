import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["option1", "option2", "option3"];

    connect() {
        super.connect();
    }

    toggle(event) {
        const selectedOption = event.target.value;

        const targets = ["option1", "option2", "option3"];

        targets.forEach((targetName) => {
            const targetElement = this[`${targetName}Target`];
            if (targetElement) {
                if (targetName === selectedOption) {
                    targetElement.classList.remove('hidden');
                } else {
                    targetElement.classList.add('hidden');
                }
            }
        });
    }
}