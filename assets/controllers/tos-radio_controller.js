import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["LINK", "TEXT_EDITOR"];

    connect() {
        console.log('tos radio controller connected');
    }

    toggle(event) {
        if (event.target.value == "LINK") {
            this.LINKTarget.classList.remove('hidden');
            this.TEXT_EDITORTarget.classList.add('hidden');
        } else {
            this.LINKTarget.classList.add('hidden');
            this.TEXT_EDITORTarget.classList.remove('hidden');
        }
    }
}
