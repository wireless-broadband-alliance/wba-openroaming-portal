import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["platformMode", "userVerification", "on", "off"];

    connect() {
        super.connect();
    }

    toggle(event) {
        if (
            event.target.value === "ON" ||
            event.target.value === "On" ||
            event.target.value === "true" ||
            event.target.value === "Demo" ||
            event.target.value === "LINK"
        ) {
            this.onTarget.classList.remove("hidden");
            this.offTarget.classList.add("hidden");
        } else {
            this.onTarget.classList.add("hidden");
            this.offTarget.classList.remove("hidden");
        }
    }

    togglePlatformMode(event) {
        // Get selected PLATFORM_MODE value
        const platformMode = this.platformModeTargets.find(input => input.checked)?.value;
        console.log("PLATFORM_MODE:", platformMode);

        // Get selected USER_VERIFICATION value
        const userVerification = this.userVerificationTargets.find(input => input.checked)?.value;
        console.log("USER_VERIFICATION:", userVerification);

        // Handle SVG visibility for PLATFORM_MODE
        this.platformModeTargets.forEach(input => {
            const parentElement = input.closest('label'); // Parent label
            const svgElement = parentElement.querySelector('.custom-radio'); // SVG container

            if (svgElement) {
                if (input.checked) {
                    svgElement.classList.remove('hidden'); // Show SVG for selected
                } else {
                    svgElement.classList.add('hidden'); // Hide SVG for non-selected
                }
            }
        });

        // Handle USER_VERIFICATION based on PLATFORM_MODE
        this.userVerificationTargets.forEach(input => {
            const parentElement = input.closest('label'); // Parent label
            const svgElement = parentElement.querySelector('.custom-radio'); // SVG container

            if (platformMode === 'Live') {
                if (input.value === 'ON') {
                    input.checked = true; // Force "ON" to be selected
                    svgElement.classList.remove('hidden'); // Show "ON" SVG
                } else {
                    input.checked = false; // Prevent "OFF" from being selected
                    svgElement.classList.add('hidden'); // Hide "OFF" SVG
                }
            } else if (platformMode === 'Demo') {
                // Handle SVG visibility based on input selection for Demo
                if (input.checked) {
                    svgElement.classList.remove('hidden'); // Show selected SVG
                } else {
                    svgElement.classList.add('hidden'); // Hide non-selected SVG
                }
            }
        });
    }
}
