import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["testButton", "buttonLabel", "waitingWidget", "resultMessage"];

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

            this.showResult(data.status, data.message);
        } catch (error) {
            this.showResult("error", "An unexpected error occurred while testing.");
        } finally {
            this.testButtonTarget.disabled = false;
            this.buttonLabelTarget.textContent = "Test Certificates";
            this.waitingWidgetTarget.classList.add("hidden");
        }
    }

    showResult(status, message) {
        const color = status === "success" ? "green" : "red";

        this.resultMessageTarget.innerHTML = `
            <div class="mt-4 p-4 border rounded-lg bg-${color}-50 border-${color}-200 text-${color}-700 text-center">
                ${message}
            </div>
        `;
        this.resultMessageTarget.classList.remove("hidden");
    }
}

