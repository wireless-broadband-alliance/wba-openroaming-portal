import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'content'];

    connect() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.add('hidden');
        }
    }

    open(event) {
        const domainId = event.params.id;
        if (!this.hasModalTarget) return;

        this.modalTarget.classList.remove('hidden');

        if (domainId) {
            this.loadForm(domainId);
        }
    }

    close() {
        if (!this.hasModalTarget) return;
        this.modalTarget.classList.add('hidden');
        if (this.hasContentTarget) {
            this.contentTarget.innerHTML = '';
        }
    }

    stop(event) {
        event.stopPropagation();
    }

    async loadForm(domainId) {
        if (!this.hasContentTarget) return;

        this.contentTarget.innerHTML = `
            <div class="flex justify-center py-8">
                <span class="text-sm text-gray-500">Loading…</span>
            </div>
        `;

        const response = await fetch(`/dashboard/settings/domains/edit${domainId}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });

        this.contentTarget.innerHTML = await response.text();
    }
}
