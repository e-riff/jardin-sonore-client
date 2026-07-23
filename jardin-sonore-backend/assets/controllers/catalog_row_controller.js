import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
    };

    navigate(event) {
        if (event.defaultPrevented || event.target.closest('[data-catalog-row-ignore], a, button, input, label, select, textarea')) {
            return;
        }

        window.location.assign(this.urlValue);
    }

    navigateFromKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        window.location.assign(this.urlValue);
    }
}
