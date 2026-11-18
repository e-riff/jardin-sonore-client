import InternalDialogController from './internal_dialog_controller.js';

export default class extends InternalDialogController {
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
        super.open(event);
    }

    closeAfterSubmit(event) {
        if (event.detail.success) {
            this.close();
        }
    }
}
