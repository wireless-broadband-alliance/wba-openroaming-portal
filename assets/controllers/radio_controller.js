import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["platformMode", "on", "off", "userVerificationOn", "userVerificationOff"];

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

    toggleStatus(event) {
        const input = event.target;
        console.log("Status toggled:", input.value);

        // Example PLATFORM_MODE logic with defensive checks
        if (input.name.includes("PLATFORM_MODE")) {
            if (input.value === "Live") {
                if (this.onTarget) {
                    this.onTarget.classList.remove("hidden");
                    this.offTarget.classList.add("hidden");
                }
                if (this.offTarget) {
                    this.onTarget.classList.add("hidden");
                    this.offTarget.classList.remove("hidden");
                }
            }
            if (input.value === "Demo") {
                if (this.onTarget) {
                    this.onTarget.classList.remove("hidden");
                }
                if (this.offTarget) {
                    this.offTarget.classList.add("hidden");
                }
            }
        }

        // Example USER_VERIFICATION logic with defensive checks
        if (input.name.includes("USER_VERIFICATION")) {
            const platformMode = this.platformModeTarget?.value;

            if (platformMode === "Demo") {
                if (input.value === "ON") {
                    if (this.onTarget) {
                        this.onTarget.classList.remove("hidden");
                        this.offTarget.classList.add("hidden");
                    }
                    if (this.offTarget) {
                        this.onTarget.classList.add("hidden");
                        this.offTarget.classList.remove("hidden");
                    }
                } else if (input.value === "OFF") {
                    if (this.onTarget) {
                        this.onTarget.classList.remove("hidden");
                        this.offTarget.classList.add("hidden");
                    }
                    if (this.offTarget) {
                        this.onTarget.classList.add("hidden");
                        this.offTarget.classList.remove("hidden");
                    }
                }
            } else {
                console.warn("PLATFORM_MODE is not Demo. Enforcing USER_VERIFICATION 'ON'...");
                input.value = "ON";

                if (this.onTarget) {
                    this.onTarget.classList.remove("hidden");
                    this.offTarget.classList.add("hidden");
                }
                if (this.offTarget) {
                    this.offTarget.classList.remove("hidden");
                    this.onTarget.classList.add("hidden");
                }
            }
        }
    }
}
