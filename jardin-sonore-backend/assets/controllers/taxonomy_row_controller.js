import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['edit'];

    open(event) {
        if (this.interactionIsInsideControl(event.target)) {
            return;
        }

        this.editTarget.click();
    }

    openFromKeyboard(event) {
        if (event.target !== this.element || !['Enter', ' '].includes(event.key)) {
            return;
        }

        event.preventDefault();
        this.editTarget.click();
    }

    interactionIsInsideControl(target) {
        return target instanceof Element
            && null !== target.closest('a, button, input, label, select, textarea, form, dialog, [role="dialog"]');
    }
}
