import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        peerSelector: String,
    };

    connect() {
        this.boundSync = this.sync.bind(this);
        this.element.addEventListener('input', this.boundSync);
        this.element.addEventListener('change', this.boundSync);
        window.requestAnimationFrame(() => this.sync());
    }

    disconnect() {
        this.element.removeEventListener('input', this.boundSync);
        this.element.removeEventListener('change', this.boundSync);
    }

    sync() {
        if (!this.hasPeerSelectorValue || '' === this.peerSelectorValue.trim()) {
            return;
        }

        const formElement = this.element.closest('form');
        const peerElement = formElement?.querySelector(this.peerSelectorValue);

        if (!(peerElement instanceof HTMLElement)) {
            return;
        }

        const selfFilled = this.isFilled(this.element);
        const peerFilled = this.isFilled(peerElement);

        if (selfFilled) {
            this.setDisabled(peerElement, true);
            this.setDisabled(this.element, false);

            return;
        }

        if (peerFilled) {
            this.setDisabled(this.element, true);
            this.setDisabled(peerElement, false);

            return;
        }

        this.setDisabled(this.element, false);
        this.setDisabled(peerElement, false);
    }

    isFilled(element) {
        if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)) {
            return false;
        }

        if ('file' === element.type) {
            return (element.files?.length ?? 0) > 0;
        }

        const normalizedValue = String(element.value ?? '').trim();

        if ('' === normalizedValue) {
            return false;
        }

        return !normalizedValue.startsWith('uploads/') && !normalizedValue.startsWith('/uploads/');
    }

    setDisabled(element, disabled) {
        if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)) {
            return;
        }

        element.disabled = disabled;
        element.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }
}
