import { Controller } from '@hotwired/stimulus';

/*
 * CAPTCHA widget controller (TURNSTILE | RECAPTCHA3).
 *
 * Rendered by templates/shared/_captcha_widget.html.twig through the `render_captcha()` Twig function.
 *
 * The element MUST also carry `data-live-ignore` (+ a stable id): most forms live inside Live
 * Components, and a re-render would otherwise morph away the Turnstile iframe and its
 * `cf-turnstile-response` input. Live Components keep the element, so Stimulus does not call
 * connect() again and cannot recover the widget by itself.
 *
 * Token flow:
 *  - TURNSTILE:  widget callback writes the token into the hidden captcha field;
 *                submit is blocked until a token exists.
 *  - RECAPTCHA3: a fresh token is requested on submit (tokens expire after 2 minutes),
 *                written into the hidden captcha field, then the form is re-submitted.
 */

const TURNSTILE = 'TURNSTILE';
const RECAPTCHA3 = 'RECAPTCHA3';

const TURNSTILE_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
const RECAPTCHA_SRC = 'https://www.google.com/recaptcha/api.js';
const TURNSTILE_ONLOAD = '__captchaTurnstileOnload';

const scriptPromises = new Map();
let turnstilePromise = null;

function loadScript(src) {
  if (!scriptPromises.has(src)) {
    const promise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.defer = true;
      script.addEventListener('load', resolve, { once: true });
      script.addEventListener(
        'error',
        () => {
          scriptPromises.delete(src);
          script.remove();
          reject(new Error(`Failed to load ${src}`));
        },
        { once: true },
      );
      document.head.appendChild(script);
    });
    scriptPromises.set(src, promise);
  }

  return scriptPromises.get(src);
}

function loadTurnstile() {
  if (window.turnstile) {
    return Promise.resolve(window.turnstile);
  }

  if (!turnstilePromise) {
    turnstilePromise = new Promise((resolve, reject) => {
      window[TURNSTILE_ONLOAD] = () => resolve(window.turnstile);
      loadScript(`${TURNSTILE_SRC}?render=explicit&onload=${TURNSTILE_ONLOAD}`).catch((error) => {
        turnstilePromise = null;
        reject(error);
      });
    });
  }

  return turnstilePromise;
}

function loadRecaptcha(siteKey) {
  return loadScript(`${RECAPTCHA_SRC}?render=${encodeURIComponent(siteKey)}`).then(
    () => new Promise((resolve) => window.grecaptcha.ready(() => resolve(window.grecaptcha))),
  );
}

export default class extends Controller {
  static targets = ['widget', 'status'];

  static values = {
    provider: String,
    siteKey: String,
    fieldId: String,
    action: { type: String, default: 'login' },
    waitMessage: {
      type: String,
      default: 'Please wait for the security check to finish, then try again.',
    },
    errorMessage: {
      type: String,
      default: 'The security check could not be loaded. Please refresh the page and try again.',
    },
  };

  connect() {
    this.widgetId = null;
    this.resubmitting = false;

    this.onSubmit = this.onSubmit.bind(this);
    // Capture phase on document: survives the form element being morphed by Live Components.
    document.addEventListener('submit', this.onSubmit, true);

    if (this.providerValue === TURNSTILE) {
      this.renderTurnstile();
    } else if (this.providerValue === RECAPTCHA3) {
      // Preload so the first submit does not wait for the script download.
      loadRecaptcha(this.siteKeyValue).catch(() => this.showStatus(this.errorMessageValue));
    }
  }

  disconnect() {
    document.removeEventListener('submit', this.onSubmit, true);

    if (this.widgetId !== null && window.turnstile) {
      window.turnstile.remove(this.widgetId);
    }
    this.widgetId = null;
  }

  /* ------------------------------------------------------------------ */
  /* Turnstile                                                          */
  /* ------------------------------------------------------------------ */

  async renderTurnstile() {
    if (!this.hasWidgetTarget) {
      return;
    }

    let turnstile;
    try {
      turnstile = await loadTurnstile();
    } catch {
      this.showStatus(this.errorMessageValue);
      return;
    }

    // Controller may have been disconnected while the script was loading.
    if (!this.element.isConnected || this.widgetId !== null) {
      return;
    }

    this.widgetId = turnstile.render(this.widgetTarget, {
      sitekey: this.siteKeyValue,
      action: this.actionValue,
      theme: 'light',
      callback: (token) => {
        this.token = token;
        this.hideStatus();
      },
      'expired-callback': () => {
        this.token = '';
        turnstile.reset(this.widgetId);
      },
      'error-callback': () => {
        this.token = '';
        this.showStatus(this.errorMessageValue);
      },
    });
  }

  /* ------------------------------------------------------------------ */
  /* Submit handling                                                    */
  /* ------------------------------------------------------------------ */

  onSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.contains(this.element)) {
      return;
    }

    if (this.providerValue === TURNSTILE) {
      this.guardTurnstileSubmit(event);
    } else if (this.providerValue === RECAPTCHA3) {
      this.handleRecaptchaSubmit(event, form);
    }
  }

  guardTurnstileSubmit(event) {
    if (this.token !== '') {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    this.showStatus(this.waitMessageValue);
  }

  handleRecaptchaSubmit(event, form) {
    // Second pass: the token is already in the field, let the browser submit.
    if (this.resubmitting) {
      this.resubmitting = false;
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const submitter = event.submitter ?? null;

    loadRecaptcha(this.siteKeyValue)
      .then((grecaptcha) => grecaptcha.execute(this.siteKeyValue, { action: this.actionValue }))
      .then((token) => {
        this.token = token;
        this.hideStatus();
        this.resubmitting = true;

        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        } else {
          form.submit();
        }
      })
      .catch(() => {
        this.token = '';
        this.resubmitting = false;
        this.showStatus(this.errorMessageValue);
      });
  }

  /* ------------------------------------------------------------------ */
  /* Helpers                                                            */
  /* ------------------------------------------------------------------ */

  get field() {
    return this.fieldIdValue ? document.getElementById(this.fieldIdValue) : null;
  }

  get token() {
    if (this.field?.value) {
      return this.field.value;
    }

    // Backend also accepts the input Turnstile injects next to the widget.
    return this.element.querySelector('input[name="cf-turnstile-response"]')?.value ?? '';
  }

  set token(value) {
    if (this.field) {
      this.field.value = value ?? '';
    }
  }

  showStatus(message) {
    if (!this.hasStatusTarget) {
      return;
    }
    this.statusTarget.textContent = message;
    this.statusTarget.classList.remove('hidden');
  }

  hideStatus() {
    if (!this.hasStatusTarget) {
      return;
    }
    this.statusTarget.textContent = '';
    this.statusTarget.classList.add('hidden');
  }
}
