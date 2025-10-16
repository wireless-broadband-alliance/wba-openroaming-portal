import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["target"];

    connect() {
        super.connect();
    }

    targetTargetConnected(element) {
        setTimeout(function () {
            element.remove();
        }, 5000);
    }
}
