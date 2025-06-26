import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "list", "item"];

    connect() {
        this.hideList();
    }

    filter() {
        const searchTerm = this.inputTarget.value.toLowerCase();
        this.itemTargets.forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(searchTerm) ? "" : "none";
        });
    }

    onFocus() {
        this.showList();
    }

    onBlur() {
        // Small timeout to allow clicks on list items before hiding
        setTimeout(() => this.hideList(), 150);
    }

    showList() {
        this.listTarget.classList.remove("hidden");
    }

    hideList() {
        this.listTarget.classList.add("hidden");
    }
}
