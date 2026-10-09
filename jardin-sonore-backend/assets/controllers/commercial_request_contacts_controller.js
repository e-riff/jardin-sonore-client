import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['organization', 'person', 'personMode', 'existingPerson', 'newPerson'];
    static values = {
        url: String,
        loadingText: String,
        emptyText: String,
        errorText: String,
        existingMode: String,
        newMode: String,
    };

    connect() {
        this.updatePersonMode();
        if (this.organizationTarget.value && this.personTarget.options.length <= 1) {
            this.loadPeople(false);
        }
    }

    disconnect() {
        this.pendingRequest?.abort();
    }

    organizationChanged() {
        this.loadPeople(true);
    }

    personModeChanged() {
        this.updatePersonMode();
    }

    updatePersonMode() {
        const selectedMode = this.personModeTargets.find((radio) => radio.checked)?.value;
        this.setGroupVisible(this.existingPersonTarget, selectedMode === this.existingModeValue);
        this.setGroupVisible(this.newPersonTarget, selectedMode === this.newModeValue);
    }

    setGroupVisible(group, visible) {
        group.hidden = !visible;
        group.setAttribute('aria-hidden', String(!visible));
        for (const field of group.querySelectorAll('input, select, textarea, button')) {
            field.disabled = !visible;
        }
    }

    async loadPeople(clearSelection) {
        this.pendingRequest?.abort();
        const selectedPersonId = clearSelection ? '' : this.personTarget.value;
        this.personTarget.options.length = 1;
        const organizationId = this.organizationTarget.value;
        if (!organizationId) {
            this.personTarget.disabled = false;
            return;
        }

        this.personTarget.disabled = true;
        this.addOption('', this.loadingTextValue, true);
        const pendingRequest = new AbortController();
        this.pendingRequest = pendingRequest;
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('organizationId', organizationId);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: pendingRequest.signal,
            });
            if (!response.ok) {
                throw new Error('Could not load organization contacts');
            }
            const payload = await response.json();
            if (this.pendingRequest !== pendingRequest) {
                return;
            }
            this.personTarget.options.length = 1;
            if (payload.people.length === 0) {
                this.addOption('', this.emptyTextValue, true);
            } else {
                for (const person of payload.people) {
                    this.addOption(String(person.id), person.label);
                }
                this.personTarget.value = selectedPersonId;
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.personTarget.options.length = 1;
                this.addOption('', this.errorTextValue, true);
            }
        } finally {
            if (this.pendingRequest === pendingRequest) {
                this.personTarget.disabled = false;
                this.pendingRequest = null;
            }
        }
    }

    addOption(value, label, disabled = false) {
        const option = new Option(label, value);
        option.disabled = disabled;
        this.personTarget.add(option);
    }
}
