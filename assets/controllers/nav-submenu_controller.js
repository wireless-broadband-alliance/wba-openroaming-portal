import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "container", "icon"];

    connect() {
        super.connect();
    }

    toggle() {
        this.containerTarget.classList.toggle("hidden");
        if (this.hasIconTarget) {
            this.iconTarget.classList.toggle("rotate-90");
        }
    }

    lost_focus() {
        if (!this.containerTarget.matches(":hover")) {
            this.containerTarget.classList.add("hidden");
        }
    }
}
