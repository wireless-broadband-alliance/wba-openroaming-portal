import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modalCookie", "consentForm"];

    connect() {
        console.log("CookieController connected");

        // Initialize preferences without setting any cookies
        this.cookieScopes = this.getCookiePreferences() || {
            terms: false, // Default value; no cookies set unless user interacts
        };

        this.updateCheckboxes(); // Update modal checkboxes if preferences exist
        this.checkCookies(); // Check cookie acceptance/rejection state
    }

    checkCookies() {
        const hasSavedPreferences = this.getCookie("cookies_accepted");
        const hasRejectedCookies = this.getCookie("cookies_rejected");

        // Show banner only if no decision has been made
        if (hasSavedPreferences || hasRejectedCookies) {
            this.hideBanner();
        } else {
            this.showBanner();
        }

        console.log("Current cookie preferences: ", this.cookieScopes);
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

    rejectCookies() {
        this.cookieScopes = {};
        this.clearAllCookies();
        this.setCookiesRejected();
        this.closeModal();
        this.hideBanner();
    }

    savePreferences() {
        // Save preferences based on user input in the modal
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
        // Update modal checkboxes based on stored preferences
        Object.entries(this.cookieScopes).forEach(([scope, checked]) => this.updateCheckbox(scope, checked));
    }

    setCookiePreferences() {
        // Save preferences only if cookies are explicitly accepted
        if (!this.getCookie("cookies_accepted")) {
            console.log("Cookies not accepted, preferences will not be saved.");
            return;
        }

        document.cookie = "cookie_preferences=" + JSON.stringify(this.cookieScopes) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setCookiesAccepted() {
        document.cookie = "cookies_accepted=true; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setCookiesRejected() {
        document.cookie = "cookies_rejected=true; path=/; max-age=" + 365 * 24 * 60 * 60;
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

    clearAllCookies() {
        // Clear all cookies by setting expiration to the past
        const cookies = document.cookie.split(";");
        cookies.forEach(cookie => {
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            document.cookie = name + "=; path=/; max-age=0";
        });
    }

    closeModal() {
        this.modalCookieTarget.classList.add("hidden");
    }
}
