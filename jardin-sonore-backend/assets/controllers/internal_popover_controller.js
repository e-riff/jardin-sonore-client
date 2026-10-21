import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.onPointerDown = this.onPointerDown.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);

        document.addEventListener('pointerdown', this.onPointerDown);
        document.addEventListener('keydown', this.onKeyDown);
    }

    disconnect() {
        document.removeEventListener('pointerdown', this.onPointerDown);
        document.removeEventListener('keydown', this.onKeyDown);
    }

    onPointerDown(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.element.open = false;
        }
    }

    onKeyDown(event) {
        if ('Escape' !== event.key || !this.element.open) {
            return;
        }

        this.element.open = false;
        this.element.querySelector('summary')?.focus();
    }
}
