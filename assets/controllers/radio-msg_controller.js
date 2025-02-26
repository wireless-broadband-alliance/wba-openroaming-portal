import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["on", "msg"];

    connect() {
        super.connect();

        if (this.onTarget.checked) {
            this.hide();
        } else {
            this.display();
        }
    }

    display() {
        this.msgTarget.classList.remove("hidden");
    }

    hide() {
        this.msgTarget.classList.add("hidden");
    }
}
