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
        // Get the selected platformMode value
        const platformModeValue = this.platformModeTargets.find(input => input.checked)?.value;

        console.log("Selected Platform Mode:" + platformModeValue);

        // Find the SVG icons for Demo and Live
        const demoSvg = this.platformModeTargets.find(input => input.value === "Demo")
            ?.closest('label')
            .querySelector('.custom-radio');
        const liveSvg = this.platformModeTargets.find(input => input.value === "Live")
            ?.closest('label')
            .querySelector('.custom-radio');

        // Show/Hide the correct SVG for PLATFORM_MODE
        if (platformModeValue === "Demo") {
            if (demoSvg) demoSvg.classList.remove("hidden");
            if (liveSvg) liveSvg.classList.add("hidden");
        } else if (platformModeValue === "Live") {
            if (liveSvg) liveSvg.classList.remove("hidden");
            if (demoSvg) demoSvg.classList.add("hidden");
        }

        // Handle USER_VERIFICATION logic when PLATFORM_MODE is Live
        if (platformModeValue === "Live") {
            const userVerificationOff = this.userVerificationTargets.find(input => input.value === "OFF");
            const userVerificationOn = this.userVerificationTargets.find(input => input.value === "ON");

            if (userVerificationOff && userVerificationOff.checked) {
                // Prevent UserVerification from being OFF
                userVerificationOff.checked = false;
            }
            if (userVerificationOn) {
                // Ensure UserVerification is ON
                userVerificationOn.checked = true;

                // Find the corresponding SVGs for User Verification
                const userVerificationOnSvg = userVerificationOn.closest('label')
                    .querySelector('.custom-radio');
                const userVerificationOffSvg = userVerificationOff.closest('label')
                    .querySelector('.custom-radio');
                // Show the ON SVG and hide the OFF SVG
                if (userVerificationOnSvg) userVerificationOnSvg.classList.remove("hidden");
                if (userVerificationOffSvg) userVerificationOffSvg.classList.add("hidden");
            }
        }
    }
}
