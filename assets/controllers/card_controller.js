import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["button", "info"];

    connect() {
        super.connect();
    }

    show_info() {
        this.infoTarget.classList.remove("hidden");
        this.infoTarget.classList.add("opacity-100");
    }

    hide_info() {
        this.infoTarget.classList.add("hidden");
        this.infoTarget.classList.remove("opacity-100");
    }
}
