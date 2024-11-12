import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["banner", "modal", "modalCookie", "termsCheckbox", "analytics", "functional", "marketing"];

    connect() {
        console.log("CookieController connected");

        // Show the cookie banner only if no preferences or terms acceptance is saved
        const cookiePreferences = document.cookie.includes("cookie_preferences");
        const termsAccepted = document.cookie.includes("terms_accepted");

        if (!cookiePreferences || !termsAccepted) {
            this.bannerTarget.classList.remove("hidden");
        }
    }

    showModal() {
        console.log("Displaying cookie preferences modal");
        this.modalCookieTarget.classList.remove("hidden");
    }

    closeModal() {
        console.log("Hiding cookie preferences modal");
        this.modalTarget.classList.add("hidden");
    }

    acceptCookies() {
        const preferences = {
            analytics: true,
            functional: true,
            marketing: true,
        };

        this.setCookiePreferences(preferences);
        this.setTermsAccepted();
        // Hide the cookie banner after accepting
        this.bannerTarget.classList.add("hidden");
    }

    savePreferences() {
        const preferences = {
            analytics: this.analyticsTarget.checked,
            functional: this.functionalTarget.checked,
            marketing: this.marketingTarget.checked,
        };

        console.log("Saving preferences:", preferences);
        // Save the preferences and update the cookie
        this.setCookiePreferences(preferences);

        // If the terms checkbox is checked, save it in cookies
        if (this.termsCheckboxTarget.checked) {
            this.setTermsAccepted();
        }

        this.closeModal();
        this.bannerTarget.classList.add("hidden");
    }

    setCookiePreferences(preferences) {
        document.cookie = "cookie_preferences=" + JSON.stringify(preferences) + "; path=/; max-age=" + (365 * 24 * 60 * 60);
        console.log("Cookie set with preferences:", document.cookie);
    }

    setTermsAccepted() {
        // Save terms acceptance in cookies (valid for 365 days)
        document.cookie = "terms_accepted=true; path=/; max-age=" + (365 * 24 * 60 * 60);
        console.log("Terms accepted cookie set");
    }
}