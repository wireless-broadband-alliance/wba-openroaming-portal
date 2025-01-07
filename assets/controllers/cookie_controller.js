import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modalCookie", "consentForm"];

    connect() {
        console.log("CookieController connected");

        // Initialize preferences without setting any cookies on the first page load
        this.cookieScopes = this.getCookiePreferences() || {
            // preferences: true,
            // analytics: false,
            // marketing: false,
        };

        this.updateCheckboxes();

        this.checkCookies();
    }

    checkCookies() {
        const hasAcceptedCookies = this.getCookie("cookies_accepted");
        const hasSavedPreferences = this.getCookie("cookie_preferences");

        // If either cookies_accepted or cookie_preferences exists and cookies were not rejected, hide the banner
        if ((hasAcceptedCookies || hasSavedPreferences) && !this.cookiesRejected) {
            this.hideBanner();
        }

        if (this.cookiesRejected) {
            this.hideBanner();
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
        console.log("Rejecting cookies, removing existing cookies.");

        this.clearAllCookies();

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

        this.closeModal();
        this.hideBanner();
    }

    updateCheckbox(scope, checked) {
        const checkbox = this.consentFormTarget.querySelector(`[data-scope="${scope}"]`);
        if (checkbox) checkbox.checked = checked;
    }

    updateCheckboxes() {
        Object.entries(this.cookieScopes).forEach(([scope, checked]) => this.updateCheckbox(scope, checked));
    }

    setCookiePreferences() {
        document.cookie = "cookie_preferences=" + JSON.stringify(this.cookieScopes) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setCookiesAccepted() {
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

    clearAllCookies() {
        const cookies = document.cookie.split(";");
        cookies.forEach(cookie => {
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            // Clear each cookie by setting max-age=0
            document.cookie = name + "=; path=/; max-age=0";
        });
    }

    closeModal() {
        this.modalCookieTarget.classList.add("hidden");
    }
}
