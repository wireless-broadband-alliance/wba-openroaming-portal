import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "testButton",
        "buttonLabel",
        "waitingWidget",
        "resultMessage",
        "nextPageButton",
        "statusIndicator",
        // inputs:
        "hostInput",
        "portInput",
    ];

    async runTest(event) {
        event.preventDefault();

        const url = event.currentTarget.dataset.url;
        if (!url) {
            console.error("No URL provided for the test endpoint.");
            return;
        }

        const remoteHost = this.hostInputTarget?.value?.trim() || "";
        const remotePort = parseInt(this.portInputTarget?.value, 10);

        if (!remoteHost) {
            this.showResult({status: "error", message: "Please provide a remote host."});
            return;
        }
        if (!Number.isFinite(remotePort) || remotePort <= 0) {
            this.showResult({status: "error", message: "Please provide a valid port."});
            return;
        }

        this.testButtonTarget.disabled = true;
        this.buttonLabelTarget.textContent = "Testing...";
        this.waitingWidgetTarget.classList.remove("hidden");
        this.resultMessageTarget.classList.add("hidden");
        this.resultMessageTarget.innerHTML = "";

        try {
            const payload = {
                remote_host: remoteHost,
                remote_port: remotePort,
            };

            const response = await fetch(url, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            this.showResult(data);

            if (data.status === "success") {
                this.enableNextPageButton();
            }
        } catch (error) {
            this.showResult({
                status: "error",
                message: "An unexpected error occurred while testing.",
                error: error.message,
            });
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

        // show debug info nicely
        const extraInfo = Object.entries(data)
            .filter(([key]) => !["status", "message"].includes(key))
            .map(([key, value]) => {
                // For certificates, format as pre/code block for readability
                if (key === "local_cert" || key === "local_key") {
                    return `<p class="text-xs text-gray-600"><strong>${key}:</strong><pre class="whitespace-pre-wrap break-words">${value}</pre></p>`;
                }
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

    enableNextPageButton() {
        this.nextPageButtonTarget.classList.remove(
            "bg-gray-100", "text-gray-400", "cursor-not-allowed", "pointer-events-none"
        );
        this.nextPageButtonTarget.classList.add(
            "bg-white", "text-gray-800", "hover:bg-gray-50", "focus:ring-2", "focus:ring-primary/30", "transition"
        );

        // Optional: ensure the href is set if it was disabled
        if (!this.nextPageButtonTarget.getAttribute("href")) {
            this.nextPageButtonTarget.href = this.nextPageButtonTarget.dataset.hrefEnabled;
        }
    }

    dismissResult() {
        this.resultMessageTarget.classList.add("hidden");
    }
}
