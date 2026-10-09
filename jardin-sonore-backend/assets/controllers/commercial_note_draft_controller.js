import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { projectId: Number };

    connect() {
        this.storageKey = `commercial-note-draft:${this.projectIdValue}`;
        try {
            const draft = JSON.parse(sessionStorage.getItem(this.storageKey) || 'null');
            if (!draft) {
                return;
            }
            for (const field of this.fields()) {
                if (Object.hasOwn(draft, field.name)) {
                    field.value = draft[field.name];
                }
            }
        } catch {
            // The form remains usable when browser storage is unavailable.
        }
    }

    save() {
        try {
            const draft = {};
            for (const field of this.fields()) {
                draft[field.name] = field.value;
            }
            sessionStorage.setItem(this.storageKey, JSON.stringify(draft));
        } catch {
            // Browser storage is optional.
        }
    }

    submitted(event) {
        if (event.detail.success) {
            try {
                sessionStorage.removeItem(this.storageKey);
            } catch {
                // Browser storage is optional.
            }
        }
    }

    fields() {
        return this.element.querySelectorAll('textarea[name], select[name], input[name]:not([type="hidden"]):not([type="submit"])');
    }
}
