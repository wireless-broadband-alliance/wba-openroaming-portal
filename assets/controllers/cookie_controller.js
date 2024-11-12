import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modal", "modalCookie", "termsCheckbox", "analytics", "functional", "marketing"];

    connect() {
        console.log("CookieController connected");
        this.checkCookies();
    }

    checkCookies() {
        const cookiePreferences = this.getCookie("cookie_preferences");

        if (cookiePreferences) {
            // If cookies are already accepted, hide the banner
            this.bannerTarget.classList.add("hidden");
        } else {
            // If not, show the banner
            this.bannerTarget.classList.remove("hidden");
        }
        console.log("Current cookies are: " + cookiePreferences);
    }

    showModal() {
        this.modalCookieTarget.classList.remove("hidden");
    }

    acceptCookies() {
        const preferences = {
            analytics: this.analyticsTarget.checked,
            functional: this.functionalTarget.checked,
            marketing: this.marketingTarget.checked,
        };

        this.setCookiePreferences(preferences);
        this.setTermsAccepted();
        this.closeModal();
        this.bannerTarget.classList.add("hidden");
    }

    // Set cookie preferences
    setCookiePreferences(preferences) {
        document.cookie = "cookie_preferences=" + JSON.stringify(preferences) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    setTermsAccepted() {
        document.cookie = "terms_accepted=true; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    // Close the modal
    closeModal() {
        console.log("Cookie Modal Closed");
        this.modalCookieTarget.classList.add("hidden");
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
}
