import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    static values = { openOnConnect: Boolean };

    connect() {
        if (this.openOnConnectValue && !this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    open(event) {
        event.preventDefault();
        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close(event) {
        event?.preventDefault();
        this.dialogTarget.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
