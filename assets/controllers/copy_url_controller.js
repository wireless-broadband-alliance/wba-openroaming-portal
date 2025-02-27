import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    copy(event) {
        event.preventDefault();

        // Find the closest "data-id" attribute or directly pull the required content
        const button = event.target.closest("button");
        const text = button.dataset.id;

        navigator.clipboard
            .writeText(text)
            .then(() => {
                this.showCopyAnimation(button);
            })
            .catch((err) => {
                console.error("Failed to copy text: ", err);
            });
    }

    showCopyAnimation(button) {
        // Create the "Copied!" warning
        const copiedMessage = document.createElement("span");
        copiedMessage.textContent = "Copied!";
        copiedMessage.className = `
            relative z-50 mx-4
            bg-primary text-white text-sm font-semibold
            px-2 py-1 rounded shadow-md
            animate-fadeInAndOut
        `;
        button.parentElement.appendChild(copiedMessage);

        setTimeout(() => copiedMessage.remove(), 2500);
    }
}
