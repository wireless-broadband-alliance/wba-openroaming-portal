import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "testButton",
        "buttonLabel",
        "waitingWidget",
        "resultMessage",
        "freeradiusButton",
        // inputs:
        "hostInput",
        "portInput",
        "userInput",
        "passwordInput",
        "timeoutInput"
    ];

    async runTest(event) {
        event.preventDefault();

        const url = event.currentTarget.dataset.url;
        if (!url) {
            console.error("No URL provided for the test endpoint.");
            return;
        }

        // gather values from inputs
        const remoteHost = this.hostInputTarget?.value?.trim() || "";
        const remotePort = parseInt(this.portInputTarget?.value || "22", 10);
        const remoteUser = this.userInputTarget?.value?.trim() || "";
        const remotePassword = this.passwordInputTarget?.value?.trim() || "";

        const timeout = parseInt(this.timeoutInputTarget?.value || "5", 10);

        // very small client-side validation
        if (!remoteHost) {
            this.showResult({ status: "error", message: "Please provide a remote host." });
            return;
        }
        if (!Number.isFinite(remotePort) || remotePort <= 0) {
            this.showResult({ status: "error", message: "Please provide a valid port." });
            return;
        }
        if (!remoteUser) {
            this.showResult({ status: "error", message: "Please provide an SSH user." });
            return;
        }

        // Prepare UI
        this.testButtonTarget.disabled = true;
        this.buttonLabelTarget.textContent = "Testing...";
        this.waitingWidgetTarget.classList.remove("hidden");
        this.resultMessageTarget.classList.add("hidden");
        this.resultMessageTarget.innerHTML = "";

        try {
            const payload = {
                remote_host: remoteHost,
                remote_port: remotePort,
                remote_user: remoteUser,
                remote_password: remotePassword,
                timeout: timeout
            };

            const response = await fetch(url, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Content-Type": "application/json",
                    // If you use Symfony's CSRF token, add: "X-CSRF-Token": "<token>"
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            this.showResult(data);

            if (data.status === "success") {
                this.enableFreeradiusButton();
            }
        } catch (error) {
            this.showResult({ status: "error", message: "An unexpected error occurred while testing." });
        } finally {
            this.testButtonTarget.disabled = false;
            this.buttonLabelTarget.textContent = "Test Certificates";
            this.waitingWidgetTarget.classList.add("hidden");
        }
    }

    showResult(data) {
        const isSuccess = data.status === "success";
        const color = isSuccess ? "green" : "red";
        const title = isSuccess ? "Test Successful" : "Test Failed";

        // show debug nicely if object
        const extraInfo = Object.entries(data)
            .filter(([key]) => !["status", "message"].includes(key))
            .map(([key, value]) => {
                const display = typeof value === "object" ? JSON.stringify(value, null, 2) : value;
                return `<p class="text-xs text-gray-600"><strong>${key}:</strong> <pre>${display}</pre></p>`;
            })
            .join("");

        this.resultMessageTarget.innerHTML = `
            <div class="mt-6 p-5 border border-${color}-300 rounded-xl bg-${color}-50 text-${color}-800 shadow-sm">
                <h4 class="font-semibold text-lg text-center mb-1">${title}</h4>
                <p class="text-sm text-${color}-700 text-center mb-2">${data.message}</p>
                ${extraInfo}
                <div class="mt-3 flex justify-center">
                    <button
                        class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-${color}-100 transition"
                        data-action="click->test-certificates#dismissResult"
                    >
                        OK
                    </button>
                </div>
            </div>
        `;

        this.resultMessageTarget.classList.remove("hidden");
    }

    enableFreeradiusButton() {
        this.freeradiusButtonTarget.classList.remove(
            "bg-gray-100", "text-gray-400", "cursor-not-allowed", "pointer-events-none"
        );
        this.freeradiusButtonTarget.classList.add(
            "bg-white", "text-gray-800", "hover:bg-gray-50", "focus:ring-2", "focus:ring-primary/30", "transition"
        );
    }

    dismissResult() {
        this.resultMessageTarget.classList.add("hidden");
        this.resultMessageTarget.innerHTML = "";
    }
}
