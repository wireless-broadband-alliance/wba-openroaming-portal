import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "warning"];

    connect() {
        this.checkFrequencyOnLoad();
    }

    checkChange(event) {
        const cron = event.target.value.trim();
        this.updateFrequency(cron);
    }

    checkFrequencyOnLoad() {
        const cron = this.inputTarget.value.trim();
        this.updateFrequency(cron);
    }

    updateFrequency(cron) {
        const hasFrequency = this.hasRelevantFrequency(cron);

        if (hasFrequency) {
            this.warningTarget.style.display = "block";
        } else {
            this.warningTarget.style.display = "none";
        }
    }

    hasRelevantFrequency(cron) {
        if (!cron) {
            return false;
        }

        const parts = cron.split(/\s+/);
        if (parts.length < 2) {
            return false;
        }

        const minuteFrequency = this.parseFrequency(parts[0]);
        const hourFrequency = this.parseFrequency(parts[1]);

        return minuteFrequency > 1 || hourFrequency > 1;
    }

    parseFrequency(part) {
        if (part === "*") {
            return 1;
        }

        const match = part.match(/^\*\/(\d+)$/);
        if (match) {
            return parseInt(match[1], 10);
        }

        return 1;
    }
}
