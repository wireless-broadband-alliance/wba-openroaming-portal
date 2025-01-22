import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "button"];

    connect() {
        console.log("TermsWidgetController connected");

        // Load the saved state of the terms checkbox from localStorage, if allowed by cookies
        const hasCookies = this.getCookie("cookies_accepted") || this.getCookie("cookie_preferences");
        if (hasCookies) {
            const savedTermsState = localStorage.getItem("termsAccepted");
            if (savedTermsState !== null) {
                this.updateTermsCheckbox(JSON.parse(savedTermsState));
            }
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    showModal(event) {
        event.preventDefault();
        // Show the modal if the terms checkbox is not checked
        if (!this.agreeTermsTarget.checked) {
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
        console.log(`Checkbox changed, new state: ${isChecked}`);

        if (isChecked) {
            console.log("Terms accepted, hiding modal.");
            this.confirmationModalTarget.classList.add("hidden");

            // Save the terms state only if cookies are allowed
            const hasCookies = this.getCookie("cookies_accepted") || this.getCookie("cookie_preferences");
            if (hasCookies) {
                console.log("Cookies are enabled, saving terms state.");
                this.saveTermsState(isChecked);
            } else {
                console.log("Cookies not enabled, terms state will not be saved.");
            }
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    saveTermsState(accepted) {
        console.log(`Saving terms state: ${accepted}`);
        localStorage.setItem("termsAccepted", accepted);
    }

    updateTermsCheckbox(accepted) {
        if (this.agreeTermsTarget) {
            this.agreeTermsTarget.checked = accepted;
        }
    }

    toggleSubmitButtons() {
        const isChecked = this.agreeTermsTarget?.checked || false;
        for (let button of this.buttonTargets) {
            // Set or remove the "disabled" property
            button.disabled = !isChecked;

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
        return parts.length === 2 ? parts.pop().split(";").shift() : null;
    }
}
