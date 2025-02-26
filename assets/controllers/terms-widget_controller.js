import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "button"];

    connect() {
        const isEEAUser = parseInt(this.element.dataset.isEeaUser, 10);

        // Load the saved state of the terms checkbox from localStorage, if allowed by cookies
        const hasCookies = this.getCookie("cookies_accepted") || this.getCookie("cookie_preferences");
        if (hasCookies || isEEAUser !== 1) {
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
            this.confirmationModalTarget.classList.remove("hidden");
        } else {
            // Get href of clicked link
            window.location.href = event.currentTarget.getAttribute("href");
        }
    }

    handleCheckboxChange() {
        const isChecked = this.agreeTermsTarget.checked;

        if (isChecked) {
            this.confirmationModalTarget.classList.add("hidden");
            // Save the terms state only if cookies are allowed
            const hasCookies = this.getCookie("cookies_accepted") || this.getCookie("cookie_preferences");
            const isEEAUser = parseInt(this.element.dataset.isEeaUser, 10);
            if (hasCookies || isEEAUser !== 1) {
                this.saveTermsState(isChecked);
            }
        } else {
            // Remove the saved terms state from the localStorage if the user unchecks
            localStorage.removeItem("termsAccepted");
        }

        // Enable/Disable submit buttons based on the checkbox state
        this.toggleSubmitButtons();
    }

    saveTermsState(accepted) {
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
            // btn-disabled (for general buttons)
            button.classList.toggle("btn-disabled", !isChecked);

            // btn-secondary-disabled (for the specific login button)
            if (button.classList.contains("btn-secondary")) {
                button.classList.toggle("btn-secondary-disabled", !isChecked);
            }
        }
    }

    closeConfirmationModal() {
        this.confirmationModalTarget.classList.add("hidden");
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        return parts.length === 2 ? parts.pop().split(";").shift() : null;
    }
}
