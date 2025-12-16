import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['hiddenBlock', 'visibleBlock'];
  static values = { opened: { type: Boolean, default: false } };

  connect() {
    this._updateVisibility();
  }

  toggle() {
    this.openedValue = !this.openedValue;
    this._updateVisibility();
  }

  _updateVisibility() {
    if (this.openedValue) {
      this.visibleBlockTarget.classList.remove('hidden');
      this.hiddenBlockTarget.classList.add('hidden');
    } else {
      this.visibleBlockTarget.classList.add('hidden');
      this.hiddenBlockTarget.classList.remove('hidden');
    }
  }
}
