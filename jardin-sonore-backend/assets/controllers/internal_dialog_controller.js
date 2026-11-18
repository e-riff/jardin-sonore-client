import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    static values = { openOnConnect: Boolean };

    connect() {
        this.onBeforeCache = () => this.close();
        document.addEventListener('turbo:before-cache', this.onBeforeCache);
        if (this.openOnConnectValue && !this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onBeforeCache);
        if (this.hasDialogTarget) {
            this.close();
        }
    }

    dialogTargetConnected(dialog) {
        this.onNativeClose = () => this.markClosed();
        this.onNativeCancel = (event) => this.close(event);
        dialog.addEventListener('close', this.onNativeClose);
        dialog.addEventListener('cancel', this.onNativeCancel);
    }

    dialogTargetDisconnected(dialog) {
        dialog.removeEventListener('close', this.onNativeClose);
        dialog.removeEventListener('cancel', this.onNativeCancel);
        if (dialog.open) {
            dialog.close();
        }
        this.markClosed();
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
