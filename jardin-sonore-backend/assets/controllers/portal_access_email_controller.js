import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.organizationElement = this.element.form.querySelector('[data-portal-access-email-target="organization"]');
        this.emailElement = this.element.form.querySelector('[data-portal-access-email-target="email"]');
        this.contactTypeElement = this.element.form.querySelector('[data-portal-access-email-target="contact-type"]');
        this.personFirstNameElement = this.element.form.querySelector('[data-portal-access-email-target="person-first-name"]');
        this.personLastNameElement = this.element.form.querySelector('[data-portal-access-email-target="person-last-name"]');
        this.emailOptions = Array.from(this.element.options)
            .filter((optionElement) => '' !== optionElement.value)
            .map((optionElement) => ({ element: optionElement, organizationIds: optionElement.dataset.organizationIds.split(',') }));
        this.organizationElement.addEventListener('change', this.filterEmailChoices);
        this.element.addEventListener('change', this.prefillEmail);
        this.emailElement.addEventListener('input', this.clearLinkedEmailWhenManuallyEdited);
        this.contactTypeElement.addEventListener('change', this.toggleContactCreationFields);
        this.filterEmailChoices();
        this.toggleContactCreationFields();
    }

    disconnect() {
        this.organizationElement.removeEventListener('change', this.filterEmailChoices);
        this.element.removeEventListener('change', this.prefillEmail);
        this.emailElement.removeEventListener('input', this.clearLinkedEmailWhenManuallyEdited);
        this.contactTypeElement.removeEventListener('change', this.toggleContactCreationFields);
    }

    filterEmailChoices = () => {
        const organizationId = this.organizationElement.value;

        for (const emailOption of this.emailOptions) {
            const isVisible = emailOption.organizationIds.includes(organizationId);
            emailOption.element.disabled = !isVisible;
            emailOption.element.hidden = !isVisible;
        }

        if (this.element.selectedOptions[0]?.disabled) {
            this.element.value = '';
        }

        this.prefillEmail();
    };

    prefillEmail = () => {
        const emailAddress = this.element.value;

        if ('' !== emailAddress) {
            this.emailElement.value = emailAddress;
        }

        this.toggleContactCreationFields();
    };

    clearLinkedEmailWhenManuallyEdited = () => {
        if (this.element.value !== this.emailElement.value) {
            this.element.value = '';
        }

        this.toggleContactCreationFields();
    };

    toggleContactCreationFields = () => {
        const hasLinkedEmail = '' !== this.element.value;
        const isNewPerson = !hasLinkedEmail && 'person' === this.contactTypeElement.value;
        this.contactTypeElement.closest('.form-group').hidden = hasLinkedEmail;

        for (const personFieldElement of [this.personFirstNameElement, this.personLastNameElement]) {
            personFieldElement.required = isNewPerson;
            personFieldElement.closest('.form-group').hidden = !isNewPerson;
        }
    };
}
