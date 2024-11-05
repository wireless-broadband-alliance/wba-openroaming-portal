// controllers/terms_widget_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["agreeTerms", "confirmationModal", "button"];

    connect() {
        console.log('TermsWidgetController connected');
        this.toggleSubmitButtons();
    }

    toggleSubmitButtons() {
        console.log(`Checkbox checked: ${this.agreeTermsTarget.checked}`);

        // For each submit button add `btn-disabled` based on checkbox status
        for (let button of this.buttonTargets) {
            if (button.name === 'btn-primary' || button.name === 'btn-secondary') {
                button.disabled = !this.agreeTermsTarget.checked;
                let disabled_css = button.name === 'btn-primary' ? 'btn-disabled' : 'btn-secondary-disabled';
                if (this.agreeTermsTarget.checked) {
                    button.classList.remove(disabled_css);
                    console.log('Button enabled, btn-disabled class removed');
                } else {
                    button.classList.add(disabled_css);
                    console.log('Button disabled, btn-disabled class added');
                }
            }
        }
    }

    handleCheckboxChange() {
        console.log('Terms checkbox state changed');
        this.toggleSubmitButtons();

        // Toggle the modal display based on checkbox status
        if (this.agreeTermsTarget.checked) {
            console.log('Terms checkbox is checked, showing confirmation modal');
            this.confirmationModalTarget.classList.remove('hidden');
        } else {
            console.log('Terms checkbox is unchecked, hiding confirmation modal');
            this.confirmationModalTarget.classList.add('hidden');
        }
    }

    closeConfirmationModal() {
        console.log('Close button clicked, hiding confirmation modal');
        this.confirmationModalTarget.classList.add('hidden');
    }
}
