import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    copy(event) {
        event.preventDefault();

        // Find the closest "data-id" attribute or directly pull the required content
        const button = event.target.closest('button');
        const text = button.dataset.id;

        navigator.clipboard.writeText(text).then(() => {
            alert(`Copied: ${text}`);
        }).catch(err => {
            console.error("Failed to copy text: ", err);
        });
    }
}
