import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggle() {
        const isActive = this.element.getAttribute('aria-checked') === 'true';
        this.element.setAttribute('aria-checked', String(!isActive));
    }
}
