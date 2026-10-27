import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    static values = { openOnConnect: Boolean };

    connect() {
        if (this.openOnConnectValue && !this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    openOnConnectValueChanged(value) {
        if (value && this.hasDialogTarget && !this.dialogTarget.open) {
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
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
        this.openOnConnectValue = false;
    }

    markClosed() {
        this.openOnConnectValue = false;
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
