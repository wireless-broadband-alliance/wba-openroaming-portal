import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["start", "end", "preset"]

    connect() {
        if (!this.endTarget.value) {
            this.endTarget.value = this.formatDate(new Date())
        }
    }

    applyPreset(event) {
        const value = event.target.value
        if (!value) return

        const end = new Date()
        let start = new Date()

        switch (value) {
            case "1d":
                start.setDate(end.getDate() - 1)
                break
            case "7d":
                start.setDate(end.getDate() - 7)
                break
            case "1m":
                start.setMonth(end.getMonth() - 1)
                break
            case "1y":
                start.setFullYear(end.getFullYear()-1)
        }

        this.startTarget.value = this.formatDate(start)
        this.endTarget.value = this.formatDate(end)

        this.element.requestSubmit()
    }

    formatDate(date) {
        const pad = n => String(n).padStart(2, "0")

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
    }
}