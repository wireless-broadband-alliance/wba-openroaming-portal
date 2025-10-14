import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["code"];

    copyToClipboard(event) {
        let pre;

        if (event) {
            pre = event.currentTarget.closest('.space-y-2').querySelector('pre[data-clipboard-target]');
        }

        if (!pre) {
            pre = this.codeTarget;
        }

        navigator.clipboard.writeText(pre.innerText)
            .then(() => alert("Text copied to clipboard!"))
            .catch(() => alert("Unable to copy the text."));
    }
}
