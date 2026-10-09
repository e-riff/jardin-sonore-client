import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['existingOrganization', 'newOrganization'];

    connect() {
        this.update();
    }

    update() {
        const selectedMode = this.element.querySelector('input[name$="[organizationMode]"]:checked')?.value ?? 'existing';
        this.setGroupVisible(this.existingOrganizationTarget, selectedMode === 'existing');
        this.setGroupVisible(this.newOrganizationTarget, selectedMode === 'new');
    }

    setGroupVisible(group, visible) {
        group.hidden = !visible;
        group.setAttribute('aria-hidden', String(!visible));
        group.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !visible;
        });
    }
}
