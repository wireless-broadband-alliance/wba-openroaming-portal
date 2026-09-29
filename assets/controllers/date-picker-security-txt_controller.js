import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'input',
        'pickerWrap',
        'pickerTrigger',
        'pickerDropdown',
        'calendarGrid',
        'footerSummary',
        'dateLabel',
        'pickerArrow',
    ];

    static values = {
        translations: Object,
    };

    #selectedDate = null;
    #viewYear = new Date().getFullYear();
    #viewMonth = new Date().getMonth();

    connect() {
        this.boundClickOutside = this.handleClickOutside.bind(this);
        document.addEventListener('click', this.boundClickOutside);

        if (this.hasInputTarget && this.inputTarget.value) {
            // Split YYYY-MM-DD manually to prevent UTC timezone date shifts
            const parts = this.inputTarget.value.split('-');
            if (parts.length === 3) {
                const year = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1;
                const day = parseInt(parts[2], 10);

                const parsed = new Date(year, month, day);
                if (!isNaN(parsed.getTime())) {
                    this.#selectedDate = parsed;
                    this.#viewYear = year;
                    this.#viewMonth = month;
                }
            }
        }

        this.#updateLabel();
        this.#renderCalendar();
    }

    disconnect() {
        document.removeEventListener('click', this.boundClickOutside);
    }

    handleClickOutside(event) {
        if (!event.target.isConnected) return;

        if (!this.element.contains(event.target)) {
            this.closePicker();
        }
    }

    togglePicker(event) {
        if (event) event.stopPropagation();
        if (this.pickerDropdownTarget.classList.contains('hidden')) {
            this.openPicker();
        } else {
            this.closePicker();
        }
    }

    openPicker() {
        this.pickerDropdownTarget.classList.remove('hidden');
    }

    closePicker() {
        this.pickerDropdownTarget.classList.add('hidden');
    }

    prevMonth(event) {
        if (event) event.stopPropagation();
        this.#viewMonth--;
        if (this.#viewMonth < 0) {
            this.#viewMonth = 11;
            this.#viewYear--;
        }
        this.#renderCalendar();
    }

    nextMonth(event) {
        if (event) event.stopPropagation();
        this.#viewMonth++;
        if (this.#viewMonth > 11) {
            this.#viewMonth = 0;
            this.#viewYear++;
        }
        this.#renderCalendar();
    }

    clickDay(event) {
        if (event) event.stopPropagation();
        const year = parseInt(event.currentTarget.dataset.year, 10);
        const month = parseInt(event.currentTarget.dataset.month, 10);
        const day = parseInt(event.currentTarget.dataset.day, 10);

        this.#selectedDate = new Date(year, month, day);
        this.#renderCalendar();
        this.#updateFooter();
    }

    clearDate(event) {
        if (event) event.stopPropagation();
        this.#selectedDate = null;
        if (this.hasInputTarget) {
            this.inputTarget.value = '';

            // Dispatch events so form listeners / Live Component catch the update
            this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
            this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.#updateLabel();
        this.#renderCalendar();
    }

    applyDate(event) {
        if (event) event.stopPropagation();
        if (this.#selectedDate && this.hasInputTarget) {
            this.inputTarget.value = this.#formatDate(this.#selectedDate);

            // Dispatch events so form listeners / Live Component catch the update
            this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
            this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));

            this.#updateLabel();
        }
        this.closePicker();
    }

    t(key) {
        return this.translationsValue?.[key] || key;
    }

    #sameDay(d1, d2) {
        if (!d1 || !d2) return false;
        return (
            d1.getFullYear() === d2.getFullYear() &&
            d1.getMonth() === d2.getMonth() &&
            d1.getDate() === d2.getDate()
        );
    }

    #formatDate(date) {
        if (!date) return '';
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    #updateLabel() {
        if (!this.hasDateLabelTarget) return;

        if (this.#selectedDate) {
            this.dateLabelTarget.textContent = this.#formatDate(this.#selectedDate);
            this.dateLabelTarget.classList.remove('text-gray-500');
            this.dateLabelTarget.classList.add('text-gray-800', 'font-medium');
        } else {
            this.dateLabelTarget.textContent = this.t('pickDate') || 'Pick a date';
            this.dateLabelTarget.classList.remove('text-gray-800', 'font-medium');
            this.dateLabelTarget.classList.add('text-gray-500');
        }
    }

    #updateFooter() {
        if (!this.hasFooterSummaryTarget) return;

        if (this.#selectedDate) {
            this.footerSummaryTarget.textContent = `${this.t('selected')}: ${this.#formatDate(this.#selectedDate)}`;
        } else {
            this.footerSummaryTarget.textContent = this.t('pickDate') || '';
        }
    }

    #renderCalendar() {
        if (!this.hasCalendarGridTarget) return;

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

        let html = `
            <div class="flex items-center justify-between mb-2">
                <button type="button" data-action="click->${this.identifier}#prevMonth"
                        class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8249;</button>
                <span class="text-xs font-medium text-gray-700">${MONTHS[month]} ${year}</span>
                <button type="button" data-action="click->${this.identifier}#nextMonth"
                        class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8250;</button>
            </div>
            <div class="grid grid-cols-7 gap-[2px]">
        `;

        DAYS.forEach((d) => {
            html += `<div class="text-[10px] text-gray-400 text-center pb-1">${d}</div>`;
        });

        for (let i = 0; i < fd; i++) {
            html += `<div></div>`;
        }

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

            html += `<button type="button" class="${cls}"
                    data-action="click->${this.identifier}#clickDay"
                    data-year="${year}" data-month="${month}" data-day="${d}">${d}</button>`;
        }

        html += `</div>`;
        this.calendarGridTarget.innerHTML = html;
        this.#updateFooter();
    }
}
