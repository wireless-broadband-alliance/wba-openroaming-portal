import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modalCookie", "consentForm"];

    connect() {
        console.log("CookieController connected");
        this.cookieScopes = this.getCookiePreferences() || {
            functional: false,
            analytics: false,
            marketing: false,
        };
        this.updateCheckboxes();
        this.checkCookies();
    }

    checkCookies() {
        if (Object.values(this.cookieScopes).some(scope => scope)) {
            this.hideBanner();
        }
        // console.log("Current cookies: ", this.cookieScopes);
    }

    showModal() {
        this.modalCookieTarget.classList.remove("hidden");
    }

    acceptCookies() {
        Object.keys(this.cookieScopes).forEach(scope => {
            this.cookieScopes[scope] = true;
            this.updateCheckbox(scope, true);
        });
        this.setCookiePreferences();
        this.hideBanner();
    }

    savePreferences() {
        // Get checkbox values dynamically based on `data-scope`
        this.consentFormTarget.querySelectorAll('[data-scope]').forEach(checkbox => {
            const scope = checkbox.getAttribute('data-scope');
            this.cookieScopes[scope] = checkbox.checked;
        });
        this.setCookiePreferences();
        this.closeModal();
        this.hideBanner();
    }

    updateCheckbox(scope, checked) {
        const checkbox = this.consentFormTarget.querySelector(`[data-scope="${scope}"]`);
        if (checkbox) checkbox.checked = checked;
    }

    updateCheckboxes() {
        // Update checkboxes on page load based on stored preferences
        Object.entries(this.cookieScopes).forEach(([scope, checked]) => this.updateCheckbox(scope, checked));
    }

    hideBanner() {
        this.bannerTarget.classList.add("hidden");
    }

    setCookiePreferences() {
        document.cookie = "cookie_preferences=" + JSON.stringify(this.cookieScopes) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    getCookiePreferences() {
        const cookie = this.getCookie("cookie_preferences");
        return cookie ? JSON.parse(cookie) : null;
    }

    closeModal() {
        this.modalCookieTarget.classList.add("hidden");
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
}