import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = { url: String };

    connect() {
        this.requestVersion = 0;
        this.organizationElement = this.element.form.querySelector('[data-portal-access-email-target="organization"]');
        this.emailElement = this.element.form.querySelector('[data-portal-access-email-target="email"]');
        this.contactTypeElement = this.element.form.querySelector('[data-portal-access-email-target="contact-type"]');
        this.personFirstNameElement = this.element.form.querySelector('[data-portal-access-email-target="person-first-name"]');
        this.personLastNameElement = this.element.form.querySelector('[data-portal-access-email-target="person-last-name"]');
        this.organizationElement.addEventListener('change', this.organizationChanged);
        this.element.addEventListener('change', this.prefillEmail);
        this.emailElement.addEventListener('input', this.clearLinkedEmailWhenManuallyEdited);
        this.contactTypeElement.addEventListener('change', this.toggleContactCreationFields);

        this.tomSelect = new TomSelect(this.element, {
            valueField: 'email',
            labelField: 'label',
            searchField: ['email', 'label'],
            maxItems: 1,
            preload: false,
            create: false,
            shouldLoad: (query) => query.length >= 2 && '' !== this.organizationElement.value,
            load: (query, callback) => {
                const requestVersion = this.requestVersion;
                const organizationId = this.organizationElement.value;
                this.fetchEmailChoices(query, organizationId)
                    .then((items) => callback(requestVersion === this.requestVersion ? items : []))
                    .catch(() => callback());
            },
        });

        this.toggleContactCreationFields();
        if ('' !== this.organizationElement.value) {
            this.organizationChanged();
        }
    }

    disconnect() {
        this.requestVersion++;
        this.organizationElement.removeEventListener('change', this.organizationChanged);
        this.element.removeEventListener('change', this.prefillEmail);
        this.emailElement.removeEventListener('input', this.clearLinkedEmailWhenManuallyEdited);
        this.contactTypeElement.removeEventListener('change', this.toggleContactCreationFields);
        this.tomSelect?.destroy();
    }

    organizationChanged = async (event = null) => {
        const requestVersion = ++this.requestVersion;
        const organizationId = this.organizationElement.value;
        const selectedEmailAddress = this.tomSelect.getValue();
        this.tomSelect.clear(true);
        this.tomSelect.clearOptions();
        if (null !== event || '' !== selectedEmailAddress) this.emailElement.value = '';

        if ('' === organizationId) {
            this.emailElement.value = '';
            this.toggleContactCreationFields();

            return;
        }

        try {
            const emailChoices = await this.fetchEmailChoices('', organizationId);
            if (requestVersion !== this.requestVersion) return;
            this.tomSelect.addOptions(emailChoices);
            this.tomSelect.refreshOptions(false);
            if ('' !== selectedEmailAddress) {
                const matchingChoices = await this.fetchEmailChoices(selectedEmailAddress, organizationId);
                if (requestVersion !== this.requestVersion) return;
                const selectedEmailChoice = matchingChoices.find((emailChoice) => emailChoice.email === selectedEmailAddress);
                if (undefined !== selectedEmailChoice) {
                    this.tomSelect.addOption(selectedEmailChoice);
                    this.tomSelect.setValue(selectedEmailAddress, true);
                    this.emailElement.value = selectedEmailAddress;
                } else {
                    this.emailElement.value = '';
                }
            }
        } catch {
            if (requestVersion === this.requestVersion && '' !== selectedEmailAddress) {
                this.emailElement.value = '';
            }
        }

        if (requestVersion === this.requestVersion) this.toggleContactCreationFields();
    };

    fetchEmailChoices = async (query, organizationId = this.organizationElement.value) => {
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('organizationId', organizationId);

        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('Unable to search organization email addresses.');
        }

        const payload = await response.json();

        return payload.items;
    };

    prefillEmail = () => {
        this.requestVersion++;
        const emailAddress = this.tomSelect.getValue();
        if ('' !== emailAddress) {
            this.emailElement.value = emailAddress;
        }

        this.toggleContactCreationFields();
    };

    clearLinkedEmailWhenManuallyEdited = () => {
        if (this.tomSelect.getValue() !== this.emailElement.value) {
            this.requestVersion++;
            this.tomSelect.clear(true);
        }

        this.toggleContactCreationFields();
    };

    toggleContactCreationFields = () => {
        const hasLinkedEmail = '' !== (this.tomSelect?.getValue() ?? '');
        const isNewPerson = !hasLinkedEmail && 'person' === this.contactTypeElement.value;
        this.contactTypeElement.closest('.form-group').hidden = hasLinkedEmail;

        for (const personFieldElement of [this.personFirstNameElement, this.personLastNameElement]) {
            personFieldElement.required = isNewPerson;
            personFieldElement.closest('.form-group').hidden = !isNewPerson;
        }
    };
}
