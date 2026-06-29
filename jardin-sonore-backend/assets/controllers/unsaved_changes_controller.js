import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        dirty: Boolean,
        message: String,
    };

    connect() {
        this.dirty = this.dirtyValue;
        this.markDirty = this.markDirty.bind(this);
        this.handleSubmit = this.handleSubmit.bind(this);
        this.handleBeforeUnload = this.handleBeforeUnload.bind(this);
        this.handleBeforeVisit = this.handleBeforeVisit.bind(this);

        this.element.addEventListener('input', this.markDirty);
        this.element.addEventListener('change', this.markDirty);
        this.element.addEventListener('submit', this.handleSubmit);
        window.addEventListener('beforeunload', this.handleBeforeUnload);
        document.addEventListener('turbo:before-visit', this.handleBeforeVisit);
    }

    disconnect() {
        this.element.removeEventListener('input', this.markDirty);
        this.element.removeEventListener('change', this.markDirty);
        this.element.removeEventListener('submit', this.handleSubmit);
        window.removeEventListener('beforeunload', this.handleBeforeUnload);
        document.removeEventListener('turbo:before-visit', this.handleBeforeVisit);
    }

    markDirty() {
        this.dirty = true;
    }

    handleSubmit() {
        this.dirty = false;
    }

    handleBeforeUnload(event) {
        if (!this.dirty) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    }

    handleBeforeVisit(event) {
        if (!this.dirty || window.confirm(this.messageValue)) {
            this.dirty = false;

            return;
        }

        event.preventDefault();
    }
}
