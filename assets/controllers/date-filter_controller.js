import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['presetBtn', 'customPicker', 'pickerTrigger', 'pickerDropdown',
        'pickerArrow', 'rangeLabel', 'calendarGrid', 'footerSummary',
        'start', 'end'];
    static values = { activePreset: String };

    #rangeStart  = null;
    #rangeEnd    = null;
    #selecting   = false;
    #hoverDay    = null;
    #viewYear    = null;
    #viewMonth   = null;
    #pickerOpen  = false;
    #hoverTimer  = null;
    #clickLock   = false;   // prevents hover re-render from eating the 2nd click

    connect() {
        const now = new Date();
        // Start view on the month BEFORE current so current month is on the right
        this.#viewMonth = now.getMonth() === 0 ? 11 : now.getMonth() - 1;
        this.#viewYear  = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();

        if (this.startTarget.value) this.#rangeStart = new Date(this.startTarget.value);
        if (this.endTarget.value)   this.#rangeEnd   = new Date(this.endTarget.value);

        const active = this.activePresetValue;
        if (active) {
            this.markActive(active);
            if (active === 'custom') this.showCustomPicker();
        }
    }

    // ── Preset pills ──────────────────────────────────────────────────────────

    applyPreset(event) {
        const value = event.currentTarget.dataset.value;
        this.markActive(value);

        if (value === 'custom') {
            this.showCustomPicker();
            return;
        }

        this.hideCustomPicker();

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
                start.setHours(0, 0, 0, 0);
                end = new Date(now);
                end.setHours(23, 59, 0, 0);
                break;
            case '30d':
                start = new Date(now);
                start.setDate(now.getDate() - 30);
                start.setHours(0, 0, 0, 0);
                end = new Date(now);
                end.setHours(23, 59, 0, 0);
                break;
            case '1m':
                start = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0);
                end   = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59);
                break;
            default:
                return;
        }

        this.#submitPreset(value, start, end);
    }

    markActive(value) {
        this.presetBtnTargets.forEach(btn => {
            const on = btn.dataset.value === value;
            btn.classList.toggle('!bg-[#7DB928]', on);
            btn.classList.toggle('!text-white', on);
            btn.classList.toggle('font-medium', on);
        });
    }

    // ── Picker visibility ─────────────────────────────────────────────────────

    showCustomPicker() {
        this.customPickerTarget.classList.remove('hidden');
        this.customPickerTarget.classList.add('flex');
        this.#renderCalendars();
    }

    hideCustomPicker() {
        this.customPickerTarget.classList.add('hidden');
        this.customPickerTarget.classList.remove('flex');
        this.#closePicker();
    }

    togglePicker() {
        this.#pickerOpen ? this.#closePicker() : this.#openPicker();
    }

    #openPicker() {
        this.#pickerOpen = true;
        this.pickerDropdownTarget.classList.remove('hidden');
        this.pickerArrowTarget.textContent = '▲';
        this.pickerTriggerTarget.classList.add('!border-[#7DB928]');
        this.#renderCalendars();
    }

    #closePicker() {
        this.#pickerOpen = false;
        this.pickerDropdownTarget.classList.add('hidden');
        this.pickerArrowTarget.textContent = '▼';
        this.pickerTriggerTarget.classList.remove('!border-[#7DB928]');
    }

    // ── Calendar rendering ────────────────────────────────────────────────────

    #renderCalendars() {
        const m1 = { y: this.#viewYear,  m: this.#viewMonth };
        const m2 = this.#nextMonth(m1);
        this.calendarGridTarget.innerHTML =
          this.#buildCal(m1.y, m1.m, 'left') +
          this.#buildCal(m2.y, m2.m, 'right');
        this.#updateFooter();
        this.#updateTriggerLabel();
    }

    #nextMonth({ y, m }) {
        return m === 11 ? { y: y + 1, m: 0 } : { y, m: m + 1 };
    }

    #buildCal(year, month, side) {
        const DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];
        const MONTHS = ['January','February','March','April','May','June',
            'July','August','September','October','November','December'];
        const dim    = new Date(year, month + 1, 0).getDate();
        const fd     = new Date(year, month, 1).getDay();
        const today  = new Date();
        const eff    = (this.#selecting && this.#hoverDay) ? this.#hoverDay : this.#rangeEnd;

        let html = `<div>
            <div class="flex items-center justify-between mb-2">
                ${side === 'left'
          ? `<button type="button" data-action="click->date-filter#prevMonth"
                               class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8249;</button>`
          : `<div class="w-6"></div>`}
                <span class="text-xs font-medium text-gray-700">${MONTHS[month]} ${year}</span>
                ${side === 'right'
          ? `<button type="button" data-action="click->date-filter#nextMonth"
                               class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-base">&#8250;</button>`
          : `<div class="w-6"></div>`}
            </div>
            <div class="grid grid-cols-7 gap-[2px]">`;

        DAYS.forEach(d => {
            html += `<div class="text-[10px] text-gray-400 text-center pb-1">${d}</div>`;
        });

        for (let i = 0; i < fd; i++) html += `<div></div>`;

        for (let d = 1; d <= dim; d++) {
            const date    = new Date(year, month, d);
            const isStart = this.#sameDay(date, this.#rangeStart);
            const isEnd   = eff && this.#sameDay(date, eff);
            const isIn    = this.#between(date, this.#rangeStart, eff);
            const isToday = this.#sameDay(date, today);

            const isBlocked = this.#selecting && this.#rangeStart && (() => {
                const diff = Math.round(Math.abs(date - this.#rangeStart) / 86400000) + 1;
                return diff > 365;
            })();

            let cls = 'w-full aspect-square flex items-center justify-center text-[11px] transition-colors duration-75 ';

            if (isBlocked) {
                cls += 'text-gray-300 cursor-not-allowed ';
            } else if (isStart && isEnd) {
                cls += 'bg-[#7DB928] text-white font-medium rounded-md cursor-pointer ';
            } else if (isStart) {
                cls += 'bg-[#7DB928] text-white font-medium rounded-l-md rounded-r-none cursor-pointer ';
            } else if (isEnd) {
                cls += 'bg-[#7DB928] text-white font-medium rounded-r-md rounded-l-none cursor-pointer ';
            } else if (isIn) {
                cls += 'bg-[#7DB928]/10 text-[#3B6D11] rounded-none cursor-pointer ';
            } else if (isToday) {
                cls += 'font-medium text-[#7DB928] rounded-md hover:bg-gray-100 cursor-pointer ';
            } else {
                cls += 'text-gray-700 rounded-md hover:bg-gray-100 cursor-pointer ';
            }

            html += `<button type="button" class="${cls}"
             ${isBlocked ? 'disabled' : `data-action="click->date-filter#clickDay mouseenter->date-filter#hoverDay"`}
             data-year="${year}" data-month="${month}" data-day="${d}">${d}</button>`;
        }

        html += `</div></div>`;
        return html;
    }

    // ── Calendar interactions ─────────────────────────────────────────────────
    prevMonth() {
        if (this.#viewMonth === 0) { this.#viewMonth = 11; this.#viewYear--; }
        else this.#viewMonth--;
        this.#renderCalendars();
    }

    nextMonth() {
        if (this.#viewMonth === 11) { this.#viewMonth = 0; this.#viewYear++; }
        else this.#viewMonth++;
        this.#renderCalendars();
    }

    clickDay(event) {
        event.stopPropagation();
        event.preventDefault();

        // Block any queued hover re-render from firing after this click
        clearTimeout(this.#hoverTimer);
        this.#clickLock = true;
        setTimeout(() => { this.#clickLock = false; }, 100);

        const { year, month, day } = event.currentTarget.dataset;
        const date = new Date(+year, +month, +day);

        if (!this.#selecting || !this.#rangeStart) {
            this.#rangeStart = date;
            this.#rangeEnd   = null;
            this.#selecting  = true;
            this.#clearWarning();
        } else {
            let start = this.#rangeStart;
            let end   = date;

            if (date < this.#rangeStart) {
                start = date;
                end   = this.#rangeStart;
            } else if (this.#sameDay(date, this.#rangeStart)) {
                end = new Date(date);
            }

            // Block if over 365 days
            const days = Math.round(Math.abs(end - start) / 86400000) + 1;
            if (days > 365) {
                this.#showWarning('Maximum range is 1 year. Please select a shorter period.');
                return; // don't commit, let user pick again
            }

            this.#rangeStart = start;
            this.#rangeEnd   = end;
            this.#selecting  = false;
            this.#hoverDay   = null;
            this.#clearWarning();
        }

        this.#renderCalendars();
    }

    hoverDay(event) {
        event.stopPropagation();
        if (!this.#selecting || this.#clickLock) return;

        const { year, month, day } = event.currentTarget.dataset;
        const newHover = new Date(+year, +month, +day);

        if (this.#hoverDay && this.#sameDay(newHover, this.#hoverDay)) return;
        this.#hoverDay = newHover;

        // Show warning hint while hovering over an invalid range
        if (this.#rangeStart) {
            const days = Math.round(Math.abs(newHover - this.#rangeStart) / 86400000) + 1;
            if (days > 365) {
                this.#showWarning('Maximum range is 1 year.');
            } else {
                this.#clearWarning();
            }
        }

        clearTimeout(this.#hoverTimer);
        this.#hoverTimer = setTimeout(() => {
            if (!this.#clickLock) this.#renderCalendars();
        }, 40);
    }

    clearRange() {
        clearTimeout(this.#hoverTimer);
        this.#rangeStart = null;
        this.#rangeEnd   = null;
        this.#selecting  = false;
        this.#hoverDay   = null;
        this.#renderCalendars();
    }

    applyCustomRange() {
        if (!this.#rangeStart || !this.#rangeEnd) return;

        const start = new Date(this.#rangeStart);
        start.setHours(0, 0, 0, 0);

        const end = new Date(this.#rangeEnd);
        end.setHours(23, 59, 0, 0);

        this.#closePicker();
        this.#submitPreset('custom', start, end);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    #sameDay(a, b) {
        return a && b
          && a.getFullYear() === b.getFullYear()
          && a.getMonth()    === b.getMonth()
          && a.getDate()     === b.getDate();
    }

    #between(d, a, b) {
        if (!a || !b) return false;
        const t = d.getTime();
        const s = Math.min(a.getTime(), b.getTime());
        const e = Math.max(a.getTime(), b.getTime());
        return t > s && t < e;
    }

    #updateFooter() {
        const eff = (this.#selecting && this.#hoverDay) ? this.#hoverDay : this.#rangeEnd;

        if (!this.#rangeStart) {
            this.footerSummaryTarget.innerHTML = `<span class="text-gray-400">Select a start date</span>`;
            return;
        }
        if (!eff) {
            this.footerSummaryTarget.innerHTML = `<span class="text-[#7DB928]">Now select an end date</span>`;
            return;
        }

        const days = Math.round(Math.abs(eff - this.#rangeStart) / 86400000) + 1;
        this.footerSummaryTarget.innerHTML =
          `Selected: <strong class="text-gray-800">${days} day${days !== 1 ? 's' : ''}</strong>`;
    }

    #updateTriggerLabel() {
        const eff = (this.#selecting && this.#hoverDay) ? this.#hoverDay : this.#rangeEnd;
        const fmt = d => d
          ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
          : '…';

        if (!this.#rangeStart) {
            this.rangeLabelTarget.innerHTML = `<span class="text-gray-400">Pick a range</span>`;
            return;
        }

        this.rangeLabelTarget.innerHTML =
          `<span class="text-gray-800 font-medium">${fmt(this.#rangeStart)}</span>
             <span class="text-gray-400 mx-1">→</span>
             <span class="text-gray-800 font-medium">${fmt(eff)}</span>`;
    }

    #submitPreset(preset, start, end) {
        const form = document.createElement('form');
        form.method = 'get';
        form.action = window.location.pathname;

        [['preset', preset], ['startDate', this.formatDate(start)], ['endDate', this.formatDate(end)]]
          .forEach(([n, v]) => {
              const i = document.createElement('input');
              i.type = 'hidden'; i.name = n; i.value = v;
              form.appendChild(i);
          });

        document.body.appendChild(form);
        form.submit();
    }

    formatDate(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    #showWarning(msg) {
        let el = this.pickerDropdownTarget.querySelector('[data-range-warning]');
        if (!el) {
            el = document.createElement('div');
            el.dataset.rangeWarning = '';
            el.className = 'flex items-center gap-2 mt-2 px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs';
            el.innerHTML = `<svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 3.5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4.5zm0 7a.875.875 0 1 1 0-1.75.875.875 0 0 1 0 1.75z"/>
        </svg><span></span>`;
            // Insert it above the footer
            const footer = this.pickerDropdownTarget.querySelector('.flex.items-center.justify-between.border-t');
            this.pickerDropdownTarget.insertBefore(el, footer);
        }
        el.querySelector('span').textContent = msg;
        el.classList.remove('hidden');
    }

    #clearWarning() {
        const el = this.pickerDropdownTarget.querySelector('[data-range-warning]');
        if (el) el.classList.add('hidden');
    }
}
