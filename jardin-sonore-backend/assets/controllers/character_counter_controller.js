import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];
    static values = { limit: Number };

    connect() {
        this.update();
    }

    update() {
        const remainingCharacters = this.limitValue - this.inputTarget.value.length;
        this.outputTarget.textContent = `${remainingCharacters} caractère${Math.abs(remainingCharacters) === 1 ? '' : 's'} restant${Math.abs(remainingCharacters) === 1 ? '' : 's'}`;
        this.outputTarget.classList.toggle('character-counter--exceeded', remainingCharacters < 0);
    }
}
