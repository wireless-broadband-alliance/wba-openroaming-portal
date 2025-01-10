import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "modalMessage", "button"];

    connect() {
        console.log("TermsWidgetController connected");

        // Load the saved state of the terms checkbox from localStorage
        const savedTermsState = localStorage.getItem("termsAccepted");

        // If a saved state exists, update the checkbox accordingly
        if (savedTermsState !== null) {
            this.updateTermsState(JSON.parse(savedTermsState));
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    showModal(event) {
        event.preventDefault();
        console.log("showModal triggered, checking terms state.");

        // Get the saved preferences from localStorage
        const savedTermsState = localStorage.getItem("termsAccepted");

        // Show the modal only if terms haven't been accepted yet
        if (savedTermsState === null || savedTermsState === "false") {
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

        // Check if cookies_accepted or cookie_preferences exists
        const hasCookies = this.getCookie("cookies_accepted") || this.getCookie("cookie_preferences");

        if (isChecked && hasCookies) {
            console.log("Terms accepted and valid cookies exist, saving state.");
            this.updateTermsState(isChecked);
        } else if (!hasCookies) {
            console.log("Cannot accept terms without valid cookies.");
        }

        if (isChecked) {
            console.log("Terms accepted, hiding modal.");
            this.confirmationModalTarget.classList.add("hidden");
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    updateTermsState(accepted) {
        console.log(`Updating terms state to: ${accepted}`);

        // Save the state of the terms checkbox in localStorage
        localStorage.setItem("termsAccepted", accepted);

        // Update the checkbox state visually
        if (this.agreeTermsTarget) {
            this.agreeTermsTarget.checked = accepted;
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    toggleSubmitButtons() {
        const isChecked = this.agreeTermsTarget?.checked || false;

        for (let button of this.buttonTargets) {
            // btn-disabled (for general buttons)
            button.classList.toggle("btn-disabled", !isChecked);

            // btn-secondary-disabled (for the specific login button)
            if (button.classList.contains("btn-secondary")) {
                button.classList.toggle("btn-secondary-disabled", !isChecked);
            }
        }
    }

    closeConfirmationModal() {
        console.log("Close button clicked, hiding modal.");
        this.confirmationModalTarget.classList.add("hidden");
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
        return null;
    }
}
