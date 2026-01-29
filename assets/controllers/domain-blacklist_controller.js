import { Controller } from '@hotwired/stimulus';

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

        const response = await fetch(`/dashboard/settings/domains/edit/${domainId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) throw new Error('Network error');

        const html = await response.text();
        this.contentTarget.innerHTML = html;

        // Attach submit handler for the loaded form
        const form = this.contentTarget.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(form);
                const action = form.action;

                const res = await fetch(action, {
                    method: form.method,
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const json = await res.json();

                if (json.success) {
                    // Form submitted successfully → close modal
                    this.close();

                    // Optional: update the table row dynamically
                    // Example: find row by data-id and update its text
                    const row = document.querySelector(`tr[data-domain-id="${json.id}"]`);
                    if (row) {
                        row.querySelector('.pattern-cell').textContent = json.pattern;
                        row.querySelector('.type-cell').textContent = json.type;
                    }

                    window.location.reload();
                } else {
                    // If form returned HTML (validation errors), replace modal content
                    const errorHtml = await res.text();
                    this.contentTarget.innerHTML = errorHtml;
                }
            });
        }
    }
}
