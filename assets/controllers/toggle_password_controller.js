import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        visibleLabel: { type: String, default: "Show" },
        visibleIcon: { type: String, default: "Default" },
        hiddenLabel: { type: String, default: "Hide" },
        hiddenIcon: { type: String, default: "Default" },
        buttonClasses: Array,
    };

    isDisplayed = false;
    visibleIcon = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M15.0007 12C15.0007 13.6569 13.6576 15 12.0007 15C10.3439 15 9.00073 13.6569 9.00073 12C9.00073 10.3431 10.3439 9 12.0007 9C13.6576 9 15.0007 10.3431 15.0007 12Z" stroke="#545F71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M2.45898 12C3.73326 7.94288 7.52354 5 12.0012 5C16.4788 5 20.2691 7.94291 21.5434 12C20.2691 16.0571 16.4788 19 12.0012 19C7.52354 19 3.73324 16.0571 2.45898 12Z" stroke="#545F71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>`;
    hiddenIcon = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M3.00073 3L6.58989 6.58916M21.0007 21L17.4119 17.4112M13.8756 18.8246C13.2684 18.9398 12.6419 19 12.0012 19C7.52354 19 3.73324 16.0571 2.45898 12C2.80588 10.8955 3.33924 9.87361 4.02217 8.97118M9.87941 9.87868C10.4223 9.33579 11.1723 9 12.0007 9C13.6576 9 15.0007 10.3431 15.0007 12C15.0007 12.8284 14.6649 13.5784 14.1221 14.1213M9.87941 9.87868L14.1221 14.1213M9.87941 9.87868L6.58989 6.58916M14.1221 14.1213L6.58989 6.58916M14.1221 14.1213L17.4119 17.4112M6.58989 6.58916C8.14971 5.58354 10.0073 5 12.0012 5C16.4788 5 20.2691 7.94291 21.5434 12C20.8365 14.2507 19.3553 16.1585 17.4119 17.4112" stroke="#545F71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>`;

    connect() {
        if (this.visibleIconValue !== "Default") {
            this.visibleIcon = this.visibleIconValue;
        }

        if (this.hiddenIconValue !== "Default") {
            this.hiddenIcon = this.hiddenIconValue;
        }

        const button = this.createButton();

        this.element.insertAdjacentElement("afterend", button);
        this.dispatchEvent("connect", { element: this.element, button });
    }

    /**
     * @returns {HTMLButtonElement}
     */
    createButton() {
        const button = document.createElement("button");
        button.type = "button";
        button.classList.add(...this.buttonClassesValue);
        button.setAttribute("tabindex", "-1");
        button.addEventListener("click", this.toggle.bind(this));
        button.innerHTML = `${this.visibleIcon} ${this.visibleLabelValue}`;
        return button;
    }

    /**
     * Toggle input type between "text" or "password" and update label accordingly
     */
    toggle(event) {
        this.isDisplayed = !this.isDisplayed;
        const toggleButtonElement = event.currentTarget;
        toggleButtonElement.innerHTML = this.isDisplayed
            ? `${this.hiddenIcon} ${this.hiddenLabelValue}`
            : `${this.visibleIcon} ${this.visibleLabelValue}`;
        this.element.setAttribute(
            "type",
            this.isDisplayed ? "text" : "password",
        );
        this.dispatchEvent(this.isDisplayed ? "show" : "hide", {
            element: this.element,
            button: toggleButtonElement,
        });
    }

    dispatchEvent(name, payload) {
        this.dispatch(name, { detail: payload, prefix: "toggle-password" });
    }
}
