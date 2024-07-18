import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select'];

    connect() {
        this.selectTarget.addEventListener('change', this.updateUrl.bind(this));
    }

    updateUrl(event) {
        const urlParams = new URLSearchParams(window.location.search);

        // Set the 'count' parameter to the selected value
        urlParams.set('count', event.target.value);

        // Navigate to the new URL
        window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
    }
}