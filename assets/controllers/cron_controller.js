import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "warning", "label"];

    connect() {
        if (this.hasInputTarget && this.hasLabelTarget) {
            this.updateLabel(this.inputTarget.value);
        }
        this.checkFrequencyOnLoad();
    }

    onInputChange(event) {
        let value = event.target.value;
        const max = parseInt(this.inputTarget.getAttribute("max"), 10) || 1;

        if (parseInt(value, 10) > max) {
            value = max;
            this.inputTarget.value = max;
        }

        this.updateLabel(value);
        this.checkFrequencyOnLoad();
    }

    checkFrequencyOnLoad() {
        const cron = this.inputTarget.value.trim();
        this.updateFrequency(cron);
    }

    updateFrequency(cron) {
        const hasFrequency = this.hasRelevantFrequency(cron);

        if (this.hasWarningTarget) {
            this.warningTarget.style.display = hasFrequency ? "block" : "none";
        }
    }

    hasRelevantFrequency(cron) {
        if (!cron) return false;
        const parts = cron.split(/\s+/);
        if (parts.length < 2) return false;

        const minuteFrequency = this.parseFrequency(parts[0]);
        const hourFrequency = this.parseFrequency(parts[1]);

        return minuteFrequency > 1 || hourFrequency > 1;
    }

    parseFrequency(part) {
        if (part === "*") return 1;

        const match = part.match(/^\*\/(\d+)$/);
        if (match) return parseInt(match[1], 10);

        return 1;
    }

    updateLabel(value) {
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = value;
        }
    }
}
