import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'modalInner', 'content'];

    connect() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.add('hidden');
        }
    }

    open(event) {
        const domainId = event.params.id;
        if (!this.hasModalTarget) return;

        // Show overlay
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('opacity-100');

        // Animate modal inner — add a tiny delay so the transition actually runs
        if (this.hasModalInnerTarget) {
            // Reset to initial state
            this.modalInnerTarget.classList.remove('scale-100', 'opacity-100');
            this.modalInnerTarget.classList.add('scale-95', 'opacity-0');

            // Force reflow so the browser registers the initial state
            void this.modalInnerTarget.offsetWidth;

            // Apply final state — now the transition will animate
            this.modalInnerTarget.classList.remove('scale-95', 'opacity-0');
            this.modalInnerTarget.classList.add('scale-100', 'opacity-100');
        }

        // Load the form dynamically
        if (domainId && this.hasContentTarget) {
            this.loadForm(domainId);
        }
    }

    close() {
        if (!this.hasModalTarget) return;

        // Animate out modal inner
        if (this.hasModalInnerTarget) {
            this.modalInnerTarget.classList.remove('scale-100', 'opacity-100');
            this.modalInnerTarget.classList.add('scale-95', 'opacity-0');
        }

        // Wait for animation, then hide overlay
        setTimeout(() => {
            this.modalTarget.classList.add('hidden');
            if (this.hasContentTarget) this.contentTarget.innerHTML = '';
        }, 200); // duration matches transition
    }

    stop(event) {
        event.stopPropagation();
    }

    async loadForm(domainId) {
        if (!this.hasContentTarget) return;
        
        try {
            const response = await fetch(`/dashboard/settings/domains/edit/${domainId}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });

            if (!response.ok) throw new Error('Network error');

            const html = await response.text();
            this.contentTarget.innerHTML = html;
        } catch (err) {
            this.contentTarget.innerHTML = `
                <div class="text-red-500 py-4 text-center">Failed to load form.</div>
            `;
        }
    }
}
