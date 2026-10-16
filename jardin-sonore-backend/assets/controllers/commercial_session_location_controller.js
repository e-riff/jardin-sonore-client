import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    choose(event) {
        this.inputTarget.value = event.params.address;
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.inputTarget.focus();
    }
}
