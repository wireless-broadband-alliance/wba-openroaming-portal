import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "button"];

    connect() {
        fetch("/get-terms-status")
            .then((res) => res.json())
            .then((data) => {
                this.updateTermsCheckbox(data.termsAccepted);
                this.toggleSubmitButtons();
            });
    }

    showModal(event) {
        event.preventDefault();
        if (!this.agreeTermsTarget.checked) {
            this.confirmationModalTarget.classList.remove("hidden");
        } else {
            window.location.href = event.currentTarget.getAttribute("href");
        }
    }

    handleCheckboxChange() {
        const isChecked = this.agreeTermsTarget.checked;

        fetch(isChecked ? "/accept-terms" : "/reject-terms", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
        })
            .then((res) => res.json())
            .then((data) => {
                console.log(data.message);
            });

        if (isChecked) this.closeConfirmationModal();

        this.toggleSubmitButtons();
    }

    updateTermsCheckbox(accepted) {
        if (this.agreeTermsTarget) {
            this.agreeTermsTarget.checked = accepted;
        }
    }

    toggleSubmitButtons() {
        const isChecked = this.agreeTermsTarget?.checked || false;
        for (let button of this.buttonTargets) {
            button.classList.toggle("btn-disabled", !isChecked);
            if (button.classList.contains("btn-secondary")) {
                button.classList.toggle("btn-secondary-disabled", !isChecked);
            }
        }
    }

    closeConfirmationModal() {
        this.confirmationModalTarget.classList.add("hidden");
    }
}