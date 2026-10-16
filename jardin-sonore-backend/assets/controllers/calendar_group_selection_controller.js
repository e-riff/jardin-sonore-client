import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['all', 'item'];

    connect() {
        this.updateAll();
    }

    toggleAll() {
        this.itemTargets.forEach((item) => {
            if (!item.disabled) {
                item.checked = this.allTarget.checked;
            }
        });
    }

    updateAll() {
        if (!this.hasAllTarget) {
            return;
        }

        const selectableItems = this.itemTargets.filter((item) => !item.disabled);
        const selectedCount = selectableItems.filter((item) => item.checked).length;

        this.allTarget.disabled = selectableItems.length === 0;
        this.allTarget.checked = selectableItems.length > 0 && selectedCount === selectableItems.length;
        this.allTarget.indeterminate = selectedCount > 0 && selectedCount < selectableItems.length;
    }
}
