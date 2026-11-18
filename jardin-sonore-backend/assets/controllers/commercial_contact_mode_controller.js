import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['choice', 'linkForm', 'createForm'];

    connect() {
        this.change();
    }

    change() {
        const mode = this.choiceTargets.find((choice) => choice.checked)?.value ?? 'link';
        this.setFormActive(this.linkFormTarget, mode === 'link');
        this.setFormActive(this.createFormTarget, mode === 'create');
    }

    setFormActive(form, active) {
        form.hidden = !active;
        for (const field of form.elements) {
            field.disabled = !active;
        }
    }
}
