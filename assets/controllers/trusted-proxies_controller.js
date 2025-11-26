import {Controller} from '@hotwired/stimulus';

// Cookie helpers
function getCookie(name) {
  const value = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
  return value ? decodeURIComponent(value.pop()) : null;
}

function setCookie(name, value, days = 7) {
  const expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires.toUTCString()};path=/`;
}

export default class extends Controller {
  static targets = ['collection', 'template'];

  connect() {
    this.restoreFromCookie();
  }

  add(eventOrValue = '') {
    if (eventOrValue instanceof Event) {
      eventOrValue.preventDefault();
      this._addInput('');
    } else {
      this._addInput(eventOrValue);
    }
  }

  _addInput(value = '') {
    const tmpl = this.templateTarget.content.cloneNode(true);
    const index = this.collectionTarget.children.length;

    // Find input inside the template
    const input = tmpl.querySelector('input');

    if (input) {
      // Replace __name__ placeholder in name and id
      if (input.name) input.name = input.name.replace('__name__', index);
      if (input.id) input.id = input.id.replace('__name__', index);

      // Set value if restoring from cookie
      if (value) input.value = value;
    }

    this.collectionTarget.appendChild(tmpl);
    this.saveToCookie();
  }

  remove(event) {
    const item = event.target.closest('[data-collection-item]');
    if (item) {
      item.remove();
      this._reindexCollection(); // Reindex after removing an item
      this.saveToCookie();
    }
  }

  // Reindex all inputs in the collection after deletion
  _reindexCollection() {
    Array.from(this.collectionTarget.children).forEach((child, index) => {
      const input = child.querySelector('input');
      if (input) {
        if (input.name) input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
        if (input.id) input.id = input.id.replace(/_\d+$/, `_${index}`);
      }
    });
  }

  saveToCookie() {
    try {
      const cookiePrefs = getCookie('cookie_preferences');
      if (!cookiePrefs) return;

      const prefs = JSON.parse(cookiePrefs);
      if (!prefs.rememberMe) return;

      const values = Array.from(this.collectionTarget.querySelectorAll('input')).map(
          input => input.value
      );

      setCookie('trustedProxiesState', JSON.stringify(values));
    } catch (e) {
      console.error('Failed to save trusted proxies state to cookie', e);
    }
  }

  restoreFromCookie() {
    try {
      const cookiePrefs = getCookie('cookie_preferences');
      if (!cookiePrefs) return;

      const prefs = JSON.parse(cookiePrefs);
      if (!prefs.rememberMe) return;

      const saved = getCookie('trustedProxiesState');
      if (!saved) return;

      const values = JSON.parse(saved);
      values.forEach(value => this._addInput(value));
    } catch (e) {
      console.error('Failed to restore trusted proxies state from cookie', e);
    }
  }
}
