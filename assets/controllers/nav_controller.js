import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "container"];

    connect() {
        super.connect();

        // Todo: What is this?
        const alertElement = document.getElementById("alert-2");
        if (alertElement) {
            alertElement.addEventListener("animationend", () => {
                alertElement.classList.add("hidden");
            });
        }
    }

    toggle() {
        this.buttonTarget.classList.toggle("open");

        this.containerTarget.classList.toggle("hidden");

        // What is this used for?
        const bodyElement = document.body;
        if (this.containerTarget.classList.contains("hidden")) {
            bodyElement.classList.remove("overflow-hidden");
        } else {
            bodyElement.classList.add("overflow-hidden");
        }
    }
}
