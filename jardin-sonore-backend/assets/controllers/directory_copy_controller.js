import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { value: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.valueValue);
            this.element.classList.add('is-copied');
            window.setTimeout(() => this.element.classList.remove('is-copied'), 1200);
        } catch {
            this.element.classList.add('copy-failed');
            window.setTimeout(() => this.element.classList.remove('copy-failed'), 1200);
        }
    }
}
