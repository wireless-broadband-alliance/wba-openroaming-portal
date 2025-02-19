import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["modal"];

    connect() {
        super.connect();
    }

    open() {
        this.modalTarget.classList.remove("hidden");
    }

    close() {
        this.modalTarget.classList.add("hidden");
    }
}
