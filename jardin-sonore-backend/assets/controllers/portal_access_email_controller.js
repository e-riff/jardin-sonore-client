import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = { url: String };

    connect() {
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
                this.fetchEmailChoices(query)
                    .then((items) => callback(items))
                    .catch(() => callback());
            },
        });

        this.toggleContactCreationFields();
    }

    disconnect() {
        this.organizationElement.removeEventListener('change', this.organizationChanged);
        this.element.removeEventListener('change', this.prefillEmail);
        this.emailElement.removeEventListener('input', this.clearLinkedEmailWhenManuallyEdited);
        this.contactTypeElement.removeEventListener('change', this.toggleContactCreationFields);
        this.tomSelect?.destroy();
    }

    organizationChanged = async () => {
        const selectedEmailAddress = this.tomSelect.getValue();
        this.tomSelect.clear(true);
        this.tomSelect.clearOptions();

        if ('' === selectedEmailAddress || '' === this.organizationElement.value) {
            this.emailElement.value = '';
            this.toggleContactCreationFields();

            return;
        }

        try {
            const emailChoices = await this.fetchEmailChoices(selectedEmailAddress);
            const selectedEmailChoice = emailChoices.find((emailChoice) => emailChoice.email === selectedEmailAddress);
            if (undefined !== selectedEmailChoice) {
                this.tomSelect.addOption(selectedEmailChoice);
                this.tomSelect.setValue(selectedEmailAddress, true);
            } else {
                this.emailElement.value = '';
            }
        } catch {
            this.emailElement.value = '';
        }

        this.toggleContactCreationFields();
    };

    fetchEmailChoices = async (query) => {
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('organizationId', this.organizationElement.value);

        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('Unable to search organization email addresses.');
        }

        const payload = await response.json();

        return payload.items;
    };

    prefillEmail = () => {
        const emailAddress = this.tomSelect.getValue();
        if ('' !== emailAddress) {
            this.emailElement.value = emailAddress;
        }

        this.toggleContactCreationFields();
    };

    clearLinkedEmailWhenManuallyEdited = () => {
        if (this.tomSelect.getValue() !== this.emailElement.value) {
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
