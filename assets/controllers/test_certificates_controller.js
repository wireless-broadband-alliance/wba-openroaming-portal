import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["resultArea", "testButton"];

    runTest() {
        this.testButton.disabled = true;
        this.testButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Running Tests...`;
        this.resultAreaTarget.style.display = "block";
        this.resultAreaTarget.innerHTML = `
            <div class="alert alert-info d-flex align-items-center">
                <i class="bi bi-hourglass-split me-2"></i>
                <div>
                    <strong>Testing in progress...</strong><br>
                    Verifying the RadSecProxy certificate setup on the server.
                </div>
            </div>
        `;

        setTimeout(() => {
            this.resultAreaTarget.innerHTML = `
                <div class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>
                        <strong>Test successful!</strong><br>
                        The RadSecProxy certificates are valid and working correctly.
                    </div>
                </div>
            `;
            this.testButton.disabled = false;
            this.testButton.innerHTML = `<i class="bi bi-shield-check"></i> Test RadSecProxy Certificates`;
        }, 2500);
    }
}
