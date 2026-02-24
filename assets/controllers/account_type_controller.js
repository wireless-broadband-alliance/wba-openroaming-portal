import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["select", "email", "phone"]

    connect() {
        this.toggle()
    }

    toggle() {
        const value = this.selectTarget.value

        if (value === "Email") {
            this.emailTarget.classList.remove("hidden")
            this.phoneTarget.classList.add("hidden")
        } else if (value === "Phone Number") {
            this.emailTarget.classList.add("hidden")
            this.phoneTarget.classList.remove("hidden")
        }
    }
}