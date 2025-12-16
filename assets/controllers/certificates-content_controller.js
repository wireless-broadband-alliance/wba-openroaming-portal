import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'content', 'toggleAllButton'];

    connect() {
        this.openAll = false;
    }

    // Toggle one card
    toggleCard(event) {
        const card = event.currentTarget.closest(`[data-${this.identifier}-target="card"]`);
        const content = card.querySelector(`[data-${this.identifier}-target="content"]`);
        const icon = event.currentTarget.querySelector('svg');

        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    // Expand/Collapse all
    toggleAll() {
        this.openAll = !this.openAll;

        this.cardTargets.forEach((card) => {
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

        // Fetch translated texts from Twig → data attributes
        const expandedText = this.toggleAllButtonTarget.dataset.collapseText;
        const collapsedText = this.toggleAllButtonTarget.dataset.expandText;

        this.toggleAllButtonTarget.querySelector('span').textContent = this.openAll
            ? expandedText
            : collapsedText;

        // Rotate main button icon
        this.toggleAllButtonTarget.querySelector('svg').classList.toggle('rotate-180', this.openAll);
    }
}
