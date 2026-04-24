import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['presetBtn', 'customForm', 'start', 'end', 'presetInput'];
    static values = { activePreset: String };

    connect() {
        const active = this.activePresetValue;
        if (active) {
            this.markActive(active);
            if (active === 'custom') {
                this.showCustomForm();
            }
        }
    }

    applyPreset(event) {
        const value = event.currentTarget.dataset.value;
        this.markActive(value);

        if (value === 'custom') {
            this.showCustomForm();
            return;
        }

        this.hideCustomForm();

        const now = new Date();
        let start, end;

        switch (value) {
            case 'yesterday':
                start = new Date(now);
                start.setDate(now.getDate() - 1);
                start.setHours(0, 0, 0, 0);
                end = new Date(start);
                end.setHours(23, 59, 0, 0);
                break;
            case '7d':
                start = new Date(now);
                start.setDate(now.getDate() - 7);
                end = now;
                break;
            case '30d':
                start = new Date(now);
                start.setDate(now.getDate() - 30);
                end = now;
                break;
            case '1m':
                start = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0);
                end = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59);
                break;
            default:
                return;
        }

        this.startTarget.value = this.formatDate(start);
        this.endTarget.value = this.formatDate(end);

        // Submit via a temporary form so the preset param is included
        const form = document.createElement('form');
        form.method = 'get';
        form.action = this.customFormTarget.action;

        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        addField('preset', value);
        addField('startDate', this.startTarget.value);
        addField('endDate', this.endTarget.value);

        document.body.appendChild(form);
        form.submit();
    }

    markActive(value) {
        this.presetBtnTargets.forEach(btn => {
            const isActive = btn.dataset.value === value;
            btn.classList.toggle('!bg-[#7DB928]', isActive);
            btn.classList.toggle('!text-white', isActive);
            btn.classList.toggle('font-medium', isActive);
        });
    }

    showCustomForm() {
        this.customFormTarget.classList.remove('hidden');
        this.customFormTarget.classList.add('flex');
    }

    hideCustomForm() {
        this.customFormTarget.classList.add('hidden');
        this.customFormTarget.classList.remove('flex');
    }

    formatDate(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }
}
