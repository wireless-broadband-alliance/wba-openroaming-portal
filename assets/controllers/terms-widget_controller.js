import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "modalMessage", "button"];

    connect() {
        console.log('TermsWidgetController connected');

        const cookiePreferences = this.getCookiePreferences() || {};

        if (cookiePreferences.terms !== undefined) {
            this.updateTermsState(cookiePreferences.terms);
        }
    }

    showModal(event) {
        event.preventDefault();
        console.log("showModal triggered, checking terms state.");

        const cookiePreferences = this.getCookiePreferences() || {};

        if (!cookiePreferences.terms) {
            console.log("Terms not accepted, showing modal.");
            this.confirmationModalTarget.classList.remove("hidden");
        } else {
            console.log("Terms already accepted, proceeding with the action.");
            const href = event.currentTarget.getAttribute("href"); // Get href of clicked link
            window.location.href = href;
        }
    }

    handleCheckboxChange() {
        const isChecked = this.agreeTermsTarget.checked;
        this.updateTermsState(isChecked);

        if (isChecked) {
            console.log("Terms accepted, hiding modal.");
            this.confirmationModalTarget.classList.add("hidden");
        }
    }

    updateTermsState(accepted) {
        console.log(`Updating terms state to: ${accepted}`);

        // Update cookie preferences only if explicitly accepted
        if (accepted) {
            const cookiePreferences = this.getCookiePreferences() || {};
            cookiePreferences.terms = accepted;
            this.setCookiePreferences(cookiePreferences);
        }

        // Update checkbox and button states
        if (this.agreeTermsTarget) {
            this.agreeTermsTarget.checked = accepted;
        }
        this.toggleSubmitButtons();
    }

    toggleSubmitButtons() {
        const isChecked = this.agreeTermsTarget?.checked || false;

        for (let button of this.buttonTargets) {
            // btn-disabled (for general buttons)
            button.classList.toggle("btn-disabled", !isChecked);

            // btn-secondary-disabled (for the specific login button)
            if (button.classList.contains('btn-secondary')) {
                button.classList.toggle("btn-secondary-disabled", !isChecked);
            }
        }
    }

    setCookiePreferences(preferences) {
        document.cookie = "cookie_preferences=" + JSON.stringify(preferences) + "; path=/; max-age=" + 365 * 24 * 60 * 60;
    }

    getCookiePreferences() {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; cookie_preferences=`);
        if (parts.length === 2) {
            try {
                return JSON.parse(parts.pop().split(";").shift());
            } catch (error) {
                console.error("Error parsing cookie:", error);
            }
        }
        return null;
    }

    closeConfirmationModal() {
        console.log("Close button clicked, hiding modal.");
        this.confirmationModalTarget.classList.add("hidden");
    }
}
