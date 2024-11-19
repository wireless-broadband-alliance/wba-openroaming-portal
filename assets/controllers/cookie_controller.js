import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modalCookie", "consentForm"];

    connect() {
        console.log("CookieController connected");
        this.cookieScopes = this.getCookiePreferences() || {
            terms: false,
            // analytics: false,
            // marketing: false,
        };
        this.updateCheckboxes();
        this.checkCookies();
    }

    checkCookies() {
        // Check if the user has saved their preferences or accepted cookies
        const hasSavedPreferences = this.getCookie("cookies_accepted");
        if (hasSavedPreferences) {
            this.hideBanner();
        } else {
            this.showBanner();
        }
        console.log("Current cookies: ", this.cookieScopes);
    }

    showBanner() {
        this.bannerTarget.classList.remove("hidden");
    }

    hideBanner() {
        this.bannerTarget.classList.add("hidden");
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
        this.setCookiesAccepted();
        this.hideBanner();
    }

    savePreferences() {
        // Get checkbox values dynamically based on `data-scope`
        this.consentFormTarget.querySelectorAll("[data-scope]").forEach(checkbox => {
            const scope = checkbox.getAttribute("data-scope");
            this.cookieScopes[scope] = checkbox.checked;
        });
        this.setCookiePreferences();
        this.setCookiesAccepted();
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

    setCookiePreferences() {
        document.cookie = "cookie_preferences=" + JSON.stringify(this.cookieScopes) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setCookiesAccepted() {
        // Set a flag indicating the user has interacted with the cookie settings
        document.cookie = "cookies_accepted=true; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    getCookiePreferences() {
        const cookie = this.getCookie("cookie_preferences");
        return cookie ? JSON.parse(cookie) : null;
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
        return null;
    }

    closeModal() {
        this.modalCookieTarget.classList.add("hidden");
    }
}