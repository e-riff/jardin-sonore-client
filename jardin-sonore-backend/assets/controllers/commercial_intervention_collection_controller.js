import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rows', 'template', 'submit', 'addButton'];
    static values = { nextIndex: Number, maxRows: Number };

    connect() {
        this.rowsTarget.querySelectorAll('input[name$="[request_id]"]').forEach((input) => {
            input.value = this.requestId();
        });
        this.updateAddButton();
    }

    add(event) {
        event.preventDefault();

        if (this.rowCount >= this.maxRowsValue) {
            return;
        }

        const html = this.templateTarget.innerHTML
            .replaceAll('__INDEX__', String(this.nextIndexValue))
            .replace('__REQUEST_ID__', this.requestId());
        this.rowsTarget.insertAdjacentHTML('beforeend', html);
        this.nextIndexValue += 1;
        this.updateAddButton();
    }

    closeMenu(event) {
        const menu = event.currentTarget.closest('.commercial-dossier__intervention-menu');
        if (menu) {
            menu.open = false;
        }
    }

    remove(event) {
        event.preventDefault();

        if (this.rowCount > 1) {
            event.currentTarget.closest('[data-intervention-row]').remove();
            this.updateAddButton();
        }
    }

    preventDuplicateSubmit() {
        this.submitTarget.disabled = true;
    }

    get rowCount() {
        return this.rowsTarget.querySelectorAll('[data-intervention-row]').length;
    }

    updateAddButton() {
        this.addButtonTarget.disabled = this.rowCount >= this.maxRowsValue;
    }

    requestId() {
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);

        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    }
}
