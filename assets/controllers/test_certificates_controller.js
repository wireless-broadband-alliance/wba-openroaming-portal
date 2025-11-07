import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["testButton", "buttonLabel", "waitingWidget", "resultMessage", "freeradiusButton"];

    async runTest(event) {
        event.preventDefault();

        // Disable button and show spinner
        this.testButtonTarget.disabled = true;
        this.buttonLabelTarget.textContent = "Testing...";
        this.waitingWidgetTarget.classList.remove("hidden");
        this.resultMessageTarget.classList.add("hidden");
        this.resultMessageTarget.innerHTML = "";

        try {
            const response = await fetch("/dashboard/settings/certificatesManagement/radsecproxy/test/run", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Content-Type": "application/json",
                },
            });

            const data = await response.json();
            this.showResult(data);

            // Check if test passed
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

        const extraInfo = Object.entries(data)
            .filter(([key]) => !["status", "message"].includes(key))
            .map(([key, value]) => `<p class="text-xs text-gray-600"><strong>${key}:</strong> ${value}</p>`)
            .join("");

        this.resultMessageTarget.innerHTML = `
            <div class="mt-6 p-5 border border-${color}-300 rounded-xl bg-${color}-50 text-${color}-800 shadow-sm animate-fade-in-test-run transition-all duration-500">
                <h4 class="font-semibold text-lg text-center mb-1">${title}</h4>
                <p class="text-sm text-${color}-700 text-center mb-2">${data.message}</p>
                ${extraInfo}
                <div class="mt-3 flex justify-center">
                    <button
                        class="px-4 py-2 text-sm font-medium bg-white border border-${color}-300 rounded-lg text-${color}-700 hover:bg-${color}-100 transition"
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
        // Remove disabled styles
        this.freeradiusButtonTarget.classList.remove("bg-gray-100", "text-gray-400", "cursor-not-allowed", "pointer-events-none");
        this.freeradiusButtonTarget.classList.add("bg-white", "text-gray-800", "hover:bg-gray-50", "focus:ring-2", "focus:ring-primary/30", "transition");
    }

    dismissResult() {
        this.resultMessageTarget.classList.add("hidden");
        this.resultMessageTarget.innerHTML = "";
    }
}

