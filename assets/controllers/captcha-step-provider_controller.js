import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['select', 'item'];

  connect() {
    this.update();
  }

  update() {
    if (!this.hasSelectTarget) return;

    const value = this.selectTarget.value;

    this.itemTargets.forEach((element) => {
      const expected = element.getAttribute('data-captcha-step-provider-value');
      element.classList.toggle('hidden', expected !== value);
    });
  }
}
