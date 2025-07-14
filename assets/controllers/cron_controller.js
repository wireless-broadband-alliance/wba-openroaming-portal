import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'warning', 'frequencyText'];

    connect() {
        // On connect, simulate a check with current input value
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
        const frequency = this.verifyHoursAndMinutesFrequency(cron);

        if (frequency) {
            console.log(`Frequency detected: ${frequency}`);
            this.frequencyTextTarget.textContent = frequency;
            this.warningTarget.style.display = 'block';
        } else {
            console.log('No special frequency detected.');
            this.warningTarget.style.display = 'none';
            this.frequencyTextTarget.textContent = '';
        }
    }

    verifyHoursAndMinutesFrequency(cron) {
        if (!cron) {
            return null;
        }
        const parts = cron.split(/\s+/);
        if (parts.length < 2) {
            return null;
        }
        const minuteFrequency = this.parseFrequency(parts[0]);
        const hourFrequency = this.parseFrequency(parts[1]);

        if (minuteFrequency > 1 && hourFrequency > 1) {
            return 'minutes and hours';
        }
        if (minuteFrequency > 1) {
            return 'minutes';
        }
        if (hourFrequency > 1) {
            return 'hours';
        }
        return null;
    }

    parseFrequency(part) {
        if (part === '*') {
            return 1;
        }
        const match = part.match(/^\*\/(\d+)$/);
        if (match) {
            return parseInt(match[1], 10);
        }
        return 1;
    }
}
