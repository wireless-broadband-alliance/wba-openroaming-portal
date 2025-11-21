import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["card", "content", "toggleAllButton"];

    connect() {
        this.openAll = false;
    }

    toggleCard(event) {
        const card = event.currentTarget.closest("[data-card-target='card']");
        const content = card.querySelector("[data-card-target='content']");
        content.classList.toggle("hidden");
    }

    toggleAll() {
        this.openAll = !this.openAll;
        const allContents = this.cardTargets.map(card => card.querySelector("[data-card-target='content']"));
        allContents.forEach(content => {
            if (this.openAll) {
                content.classList.remove("hidden");
            } else {
                content.classList.add("hidden");
            }
        });
        this.toggleAllButtonTarget.textContent = this.openAll ? "Collapse All" : "Expand All";
    }
}
