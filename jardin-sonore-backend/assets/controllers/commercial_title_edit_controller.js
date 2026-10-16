import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['display', 'form', 'input'];

    edit(event) {
        if (this.displayTarget.hidden) {
            return;
        }

        if (event.type === 'keydown') {
            event.preventDefault();
        }

        this.displayTarget.hidden = true;
        this.formTarget.hidden = false;
        this.inputTarget.focus();
        this.inputTarget.select();
    }

    save() {
        if (this.formTarget.hidden || this.inputTarget.value.trim() === '') {
            return;
        }

        this.formTarget.requestSubmit();
    }

    cancel(event) {
        event.preventDefault();
        this.inputTarget.value = this.displayTarget.textContent.trim();
        this.formTarget.hidden = true;
        this.displayTarget.hidden = false;
        this.displayTarget.focus();
    }
}
