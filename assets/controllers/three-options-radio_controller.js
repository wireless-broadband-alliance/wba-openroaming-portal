import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["option1", "option2", "option3"];

    connect() {
        super.connect();
    }

    toggle(event) {
        if (event.target.value === "NOT_ENFORCED" ) {
            this.option1Target.classList.remove('hidden');
            this.option2Target.classList.add('hidden');
            this.option3Target.classList.add('hidden');
        } if (event.target.value === "ENFORCED_FOR_LOCAL" ) {
            this.option1Target.classList.add('hidden');
            this.option2Target.classList.remove('hidden');
            this.option3Target.classList.add('hidden');
        } if (event.target.value === "ENFORCED_FOR_ALL" ) {
            this.option1Target.classList.add('hidden');
            this.option2Target.classList.add('hidden');
            this.option3Target.classList.remove('hidden');
        }
    }
}