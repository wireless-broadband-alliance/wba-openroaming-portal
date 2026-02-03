import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['page1', 'page2', 'toggle1', 'toggle2'];

    connect() {
        // Restore last page ONLY if rememberMe is enabled
        if (this.isRememberMeEnabled()) {
            const lastPage = this.getCookie('last_toggle_page');

            if (lastPage === 'page2') {
                this.showPage2(false);
                return;
            }
        }

        // Default
        this.showPage1(false);
    }

    showPage1(save = true) {
        this.page1Target.classList.remove('hidden');
        this.page2Target.classList.add('hidden');

        this.activateToggle(this.toggle1Target);
        this.deactivateToggle(this.toggle2Target);

        if (save) this.saveLastPage('page1');
    }

    showPage2(save = true) {
        this.page2Target.classList.remove('hidden');
        this.page1Target.classList.add('hidden');

        this.activateToggle(this.toggle2Target);
        this.deactivateToggle(this.toggle1Target);

        if (save) this.saveLastPage('page2');
    }

    /* Cookie helpers */
    isRememberMeEnabled() {
        const prefs = this.getCookie('cookie_preferences');
        if (!prefs) return false;

        try {
            const parsed = JSON.parse(prefs);
            return parsed.rememberMe === true;
        } catch {
            return false;
        }
    }

    saveLastPage(page) {
        if (!this.isRememberMeEnabled()) return;

        document.cookie = `last_toggle_page=${page}; path=/; max-age=${365 * 24 * 60 * 60}`;
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    activateToggle(button) {
        button.classList.add('bg-white', 'text-black', 'font-semibold', 'shadow-md');
        button.classList.remove('text-gray-700');
    }

    deactivateToggle(button) {
        button.classList.remove('bg-white', 'text-black', 'font-semibold', 'shadow-md');
        button.classList.add('text-gray-700');
    }
}
