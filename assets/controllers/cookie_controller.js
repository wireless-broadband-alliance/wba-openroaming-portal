import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modalCookie", "consentForm"];

    connect() {
        console.log("CookieController connected");

        // Initialize preferences without setting any cookies on the first page load
        this.cookieScopes = this.getCookiePreferences() || {
            terms: false, // Default value; no cookies set unless user interacts
        };

        // Check if the user rejected cookies earlier, use session storage to track it
        this.cookiesRejected = sessionStorage.getItem("cookies_rejected") === "true";

        this.updateCheckboxes(); // Update modal checkboxes if preferences exist
        this.checkCookies(); // Check cookie acceptance/rejection state
    }

    checkCookies() {
        // If no decision has been made yet, show the banner
        const hasSavedPreferences = this.getCookie("cookies_accepted");

        if (!hasSavedPreferences && !this.cookiesRejected) {
            this.showBanner(); // Show banner if no decision has been made
        } else {
            this.hideBanner(); // Hide banner if decision is made
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
        console.log("Rejecting cookies, preventing future cookie creation.");

        // Clear all cookies immediately
        this.clearAllCookies();

        // Set the session flag to prevent any future cookies from being created
        sessionStorage.setItem("cookies_rejected", "true");

        // Hide banner and modal
        this.closeModal();
        this.hideBanner();

        // Do not set any "cookies_accepted" or similar state in actual cookies
    }

    savePreferences() {
        // Save preferences based on user input in the modal
        this.consentFormTarget.querySelectorAll("[data-scope]").forEach(checkbox => {
            const scope = checkbox.getAttribute("data-scope");
            this.cookieScopes[scope] = checkbox.checked;
        });

        // If cookies were rejected previously, we need to reset the rejection flag
        if (this.cookiesRejected) {
            // Reset the rejection flag and re-enable cookie setting
            sessionStorage.removeItem("cookies_rejected");
        }

        // Save the preferences only if cookies are accepted
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
        if (this.cookiesRejected) {
            console.log("Cookies have been rejected; preferences will not be saved.");
            return;
        }

        document.cookie = "cookie_preferences=" + JSON.stringify(this.cookieScopes) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setCookiesAccepted() {
        if (this.cookiesRejected) {
            console.log("Cookies have been rejected; acceptance state will not be saved.");
            return;
        }

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