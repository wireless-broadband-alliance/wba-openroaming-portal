import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['toggle', 'card'];

  connect() {
    this.update();
  }

  toggle() {
    this.update();
  }

  update() {
    if (!this.hasToggleTarget || !this.hasCardTarget) return;

    if (this.toggleTarget.checked) {
      this.cardTarget.classList.remove('hidden');
    } else {
      this.cardTarget.classList.add('hidden');
    }
  }
}
