import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["dialog", "form", "token", "item"];

    open(event) {
        event.preventDefault();

        const {
            confirmationDialogActionValue: action,
            confirmationDialogItemValue: item,
            confirmationDialogTokenValue: token,
        } = event.currentTarget.dataset;
        if (!action || !token) {
            return;
        }

        this.formTarget.action = action;
        this.tokenTarget.value = token;
        this.itemTarget.textContent = item ?? "";
        this.dialogTarget.showModal();
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
