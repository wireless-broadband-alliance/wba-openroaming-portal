import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'input',
        'pickerWrap',
        'pickerTrigger',
        'pickerDropdown',
        'pickerArrow',
        'dateLabel',
        'calendarGrid',
        'footerSummary',
    ];
    static values = { translations: Object };

    #selectedDate = null;
    #viewYear = null;
    #viewMonth = null;
    #pickerOpen = false;

    connect() {
        if (this.inputTarget.value) {
            this.#selectedDate = new Date(this.inputTarget.value);
            this.#viewYear = this.#selectedDate.getFullYear();
            this.#viewMonth = this.#selectedDate.getMonth();
        } else {
            const now = new Date();
            this.#viewYear = now.getFullYear();
            this.#viewMonth = now.getMonth();
        }

        this._onDocClick = this.#onDocClick.bind(this);
        document.addEventListener('click', this._onDocClick);

        this.#renderCalendar();
        this.#updateTriggerLabel();
    }

    disconnect() {
        document.removeEventListener('click', this._onDocClick);
    }

    t(key) {
        return this.translationsValue?.[key] ?? key;
    }

    // ── Picker visibility ─────────────────────────────────────────────────────

    togglePicker(event) {
        event.stopPropagation();
        this.#pickerOpen ? this.#closePicker() : this.#openPicker();
    }

    #onDocClick(event) {
        if (this.#pickerOpen && !this.pickerWrapTarget.contains(event.target)) {
            this.#closePicker();
        }
    }

    #openPicker() {
        this.#pickerOpen = true;
        this.pickerDropdownTarget.classList.remove('hidden');
        this.pickerArrowTarget.textContent = '▲';
        this.pickerTriggerTarget.classList.add('!border-[#7DB928]');
        this.#renderCalendar();
    }

    #closePicker() {
        this.#pickerOpen = false;
        this.pickerDropdownTarget.classList.add('hidden');
        this.pickerArrowTarget.textContent = '▼';
        this.pickerTriggerTarget.classList.remove('!border-[#7DB928]');
    }

    // ── Calendar rendering (same cell rules as date-filter_controller) ─────────

    prevMonth() {
        if (this.#viewMonth === 0) {
            this.#viewMonth = 11;
            this.#viewYear--;
        } else this.#viewMonth--;
        this.#renderCalendar();
    }

    nextMonth() {
        if (this.#viewMonth === 11) {
            this.#viewMonth = 0;
            this.#viewYear++;
        } else this.#viewMonth++;
        this.#renderCalendar();
    }

    #renderCalendar() {
        const DAYS = [
            this.t('daySu'),
            this.t('dayMo'),
            this.t('dayTu'),
            this.t('dayWe'),
            this.t('dayTh'),
            this.t('dayFr'),
            this.t('daySa'),
        ];
        const MONTHS = [
            this.t('monthJan'),
            this.t('monthFeb'),
            this.t('monthMar'),
            this.t('monthApr'),
            this.t('monthMay'),
            this.t('monthJun'),
            this.t('monthJul'),
            this.t('monthAug'),
            this.t('monthSep'),
            this.t('monthOct'),
            this.t('monthNov'),
            this.t('monthDec'),
        ];

        const year = this.#viewYear;
        const month = this.#viewMonth;
        const dim = new Date(year, month + 1, 0).getDate();
        const fd = new Date(year, month, 1).getDay();
        const today = new Date();

        // Use this.identifier dynamically for actions
        let html = `<div class="flex items-center justify-between mb-2">
          <button type="button" data-action="click->${this.identifier}#prevMonth"
              class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8249;</button>
          <span class="text-xs font-medium text-gray-700">${MONTHS[month]} ${year}</span>
          <button type="button" data-action="click->${this.identifier}#nextMonth"
              class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8250;</button>
      </div>
      <div class="grid grid-cols-7 gap-[2px]">`;

        DAYS.forEach((d) => {
            html += `<div class="text-[10px] text-gray-400 text-center pb-1">${d}</div>`;
        });

        for (let i = 0; i < fd; i++) html += `<div></div>`;

        for (let d = 1; d <= dim; d++) {
            const date = new Date(year, month, d);
            const isSelected = this.#sameDay(date, this.#selectedDate);
            const isToday = this.#sameDay(date, today);

            let cls =
                'w-full aspect-square flex items-center justify-center text-[11px] transition-colors duration-75 ';
            if (isSelected) {
                cls += 'bg-[#7DB928] text-white font-medium rounded-md cursor-pointer ';
            } else if (isToday) {
                cls += 'font-medium text-[#7DB928] rounded-md hover:bg-gray-100 cursor-pointer ';
            } else {
                cls += 'text-gray-700 rounded-md hover:bg-gray-100 cursor-pointer ';
            }

            // Dynamic controller action binding
            html += `<button type="button" class="${cls}"
            data-action="click->${this.identifier}#clickDay"
            data-year="${year}" data-month="${month}" data-day="${d}">${d}</button>`;
        }

        html += `</div>`;
        this.calendarGridTarget.innerHTML = html;
        this.#updateFooter();
    }

    clickDay(event) {
        event.stopPropagation();
        const { year, month, day } = event.currentTarget.dataset;
        this.#selectedDate = new Date(+year, +month, +day);
        this.#renderCalendar();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    clearDate() {
        this.#selectedDate = null;
        this.inputTarget.value = '';
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.#renderCalendar();
        this.#updateTriggerLabel();
        this.#closePicker();
    }

    applyDate() {
        if (this.#selectedDate) {
            this.inputTarget.value = this.formatDate(this.#selectedDate);
            this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.#updateTriggerLabel();
        this.#closePicker();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    #sameDay(a, b) {
        return (
            a &&
            b &&
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );
    }

    #updateFooter() {
        if (!this.#selectedDate) {
            this.footerSummaryTarget.innerHTML = `<span class="text-gray-400">${this.t('pickRange')}</span>`;
            return;
        }
        const fmt = this.#selectedDate.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
        this.footerSummaryTarget.innerHTML = `${this.t('selected')}: <strong class="text-gray-800">${fmt}</strong>`;
    }

    #updateTriggerLabel() {
        if (!this.#selectedDate) {
            this.dateLabelTarget.innerHTML = `<span class="text-gray-400">${this.t('pickRange')}</span>`;
            return;
        }
        const fmt = this.#selectedDate.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
        this.dateLabelTarget.innerHTML = `<span class="text-gray-800 font-medium">${fmt}</span>`;
    }

    formatDate(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }
}
