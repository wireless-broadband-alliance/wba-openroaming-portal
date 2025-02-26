import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["code"];

    copyToClipboard() {
        const textToCopy = this.codeTarget.innerText;

        navigator.clipboard
            .writeText(textToCopy)
            .then(() => {
                alert("Text copied to clipboard!");
            })
            .catch(() => {
                alert("Unable to copy the text.");
            });
    }
}
