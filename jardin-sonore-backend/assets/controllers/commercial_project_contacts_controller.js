import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['organization', 'person', 'addTask', 'taskFields'];

    static values = {
        url: String,
        loadingText: String,
        emptyText: String,
        errorText: String,
    };

    connect() {
        this.taskChanged();
        if (this.organizationTarget.value && this.personTarget.options.length <= 1) {
            this.loadPeople(false);
        }
    }

    disconnect() {
        this.pendingRequest?.abort();
    }

    taskChanged() {
        this.taskFieldsTarget.hidden = !this.addTaskTarget.checked;
    }

    organizationChanged() {
        this.loadPeople(true);
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
