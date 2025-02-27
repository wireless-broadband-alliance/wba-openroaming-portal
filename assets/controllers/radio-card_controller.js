import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["on", "card", "input", "link", "linkInput", "textEditorInput", "button"];

    connect() {
        super.connect();

        if (this.onTarget.checked) {
            this.unblock();
        } else {
            this.block();
        }

        if (this.linkTarget.checked) {
            this.showLink();
        } else {
            this.showTextEditor();
        }
    }

    block() {
        for (let t of this.inputTargets) {
            t.readOnly = true;
            t.classList.add("cursor-not-allowed");
        }

        for (let t of this.cardTargets) {
            t.classList.remove("bg-white");
            t.classList.add("bg-disableCardsColor");
        }
        for (let t of this.buttonTargets) {
            t.classList.add("cursor-not-allowed");
            t.disabled = true;
        }
    }

    unblock() {
        for (let t of this.inputTargets) {
            t.readOnly = false;
            t.classList.remove("cursor-not-allowed");
        }

        for (let t of this.cardTargets) {
            t.classList.add("bg-white");
            t.classList.remove("bg-disableCardsColor");
        }
        for (let t of this.buttonTargets) {
            t.classList.remove("cursor-not-allowed");
            t.disabled = false;
        }
    }

    showLink() {
        this.linkInputTarget.classList.remove("hidden");
        this.textEditorInputTarget.classList.add("hidden");
    }

    showTextEditor() {
        this.linkInputTarget.classList.add("hidden");
        this.textEditorInputTarget.classList.remove("hidden");
    }
}
