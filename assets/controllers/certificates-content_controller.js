import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'content', 'toggleAllButton'];

    connect() {
        this.openAll = false;
    }

    // Called when header button clicked
    toggleCard(event) {
        const card = event.currentTarget.closest(`[data-${this.identifier}-target="card"]`);
        const content = card.querySelector(`[data-${this.identifier}-target="content"]`);
        const icon = event.currentTarget.querySelector('svg');

        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    // Expand/collapse all contents
    toggleAll() {
        this.openAll = !this.openAll;

        this.cardTargets.forEach(card => {
            const content = card.querySelector(`[data-${this.identifier}-target="content"]`);
            const icon = card.querySelector('button svg');

            if (this.openAll) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        });

        this.toggleAllButtonTarget.querySelector("span").textContent =
            this.openAll ? "Collapse All" : "Expand All";

        const btnIcon = this.toggleAllButtonTarget.querySelector("svg");
        btnIcon.classList.toggle("rotate-180", this.openAll);
    }
}
