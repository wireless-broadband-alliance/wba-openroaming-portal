import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["page1", "page2"]

    connect() {
        // Default: hide page2
        this.page1Target.classList.remove("hidden");
        this.page2Target.classList.add("hidden");

        // Check cookie only if user has "remember me"
        if (this.getCookie('rememberMe') === 'true') {
            const lastPage = this.getCookie('last_page');
            if (lastPage === 'page2') {
                this.showPage2();
            } else {
                this.showPage1();
            }
        }
    }

    showPage1() {
        this.page1Target.classList.remove("hidden");
        this.page2Target.classList.add("hidden");
        this.setLastPageCookie('page1');
    }

    showPage2() {
        this.page2Target.classList.remove("hidden");
        this.page1Target.classList.add("hidden");
        this.setLastPageCookie('page2');
    }

    // Only sets cookie if rememberMe=true
    setLastPageCookie(page) {
        if (this.getCookie('rememberMe') === 'true') {
            document.cookie = `last_page=${page}; path=/; max-age=${365 * 24 * 60 * 60}`;
        }
    }

    // Utility to read cookie
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
}
