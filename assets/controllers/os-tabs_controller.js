import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["content", "tab"]

    connect() {
        // Show the first tab by default
        if (this.hasContentTarget) {
            this.showTab(this.contentTargets[0].dataset.os);
        }
    }

    activate(event) {
        event.preventDefault();
        const os = event.currentTarget.dataset.os;
        this.showTab(os);
    }

    showTab(os) {
        // Toggle tab content
        this.contentTargets.forEach((el) => {
            el.classList.toggle("hidden", el.dataset.os !== os);
        });

        // Toggle tab button styles
        this.tabTargets.forEach((btn) => {
            const active = btn.dataset.os === os;

            btn.classList.toggle("bg-white", active);
            btn.classList.toggle("text-[#8AB742]", active);
            btn.classList.toggle("shadow-md", active);
            btn.classList.toggle("rounded-t-lg", active);
            btn.classList.toggle("border-b-2", active);
            btn.classList.toggle("border-[#8AB742]", active);
            btn.classList.toggle("-mb-[2px]", active);

            // inactive state
            btn.classList.toggle("text-gray-400", !active);
            btn.classList.toggle("hover:text-black", !active);
            btn.classList.toggle("bg-transparent", !active);
            btn.classList.toggle("border-b-2", !active);
            btn.classList.toggle("border-transparent", !active);
        });
    }
}
