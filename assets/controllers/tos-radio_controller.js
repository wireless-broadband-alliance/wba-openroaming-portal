import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["LINK", "TEXT_EDITOR", "linkContainer", "editorContainer"];

    connect() {
        console.log()
        super.connect();
    }

    toggle(event) {
        const selectedValue = this.element.querySelector('input[type="radio"]:checked')?.value;
        if (selectedValue === "LINK") {
            this.LINKTarget.classList.remove('hidden');
            this.TEXT_EDITORTarget.classList.add('hidden');
            this.showContainer(this.linkContainerTarget);
            this.hideContainer(this.editorContainerTarget);

        } else {
            this.LINKTarget.classList.add('hidden');
            this.TEXT_EDITORTarget.classList.remove('hidden');
            this.showContainer(this.editorContainerTarget);
            this.hideContainer(this.linkContainerTarget);
        }
    }

    showContainer(container) {
        container.classList.remove("hidden");
    }

    hideContainer(container) {
        container.classList.add("hidden");
    }
}
