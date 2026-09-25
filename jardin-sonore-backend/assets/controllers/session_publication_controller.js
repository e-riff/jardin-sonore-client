import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status'];

    static values = {
        url: String,
        token: String,
        published: Boolean,
        publishedLabel: String,
        unpublishedLabel: String,
        errorLabel: String,
    };

    async toggle() {
        this.element.disabled = true;

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({_token: this.tokenValue, published: this.publishedValue ? '0' : '1'}),
            });
            if (!response.ok) throw new Error('Publication request failed');

            const result = await response.json();
            if (typeof result.published !== 'boolean') throw new Error('Invalid publication response');

            this.publishedValue = result.published;
            this.statusTarget.textContent = result.published ? this.publishedLabelValue : this.unpublishedLabelValue;
            this.element.setAttribute('aria-checked', String(result.published));
        } catch {
            this.statusTarget.textContent = this.errorLabelValue;
        } finally {
            this.element.disabled = false;
        }
    }
}
