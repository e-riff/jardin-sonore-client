import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.handleAudienceSaved = this.handleAudienceSaved.bind(this);
        this.element.addEventListener('mailing:audience-saved', this.handleAudienceSaved);
        document.addEventListener('mailing:audience-saved', this.handleAudienceSaved);
        window.addEventListener('mailing:audience-saved', this.handleAudienceSaved);
    }

    disconnect() {
        this.element.removeEventListener('mailing:audience-saved', this.handleAudienceSaved);
        document.removeEventListener('mailing:audience-saved', this.handleAudienceSaved);
        window.removeEventListener('mailing:audience-saved', this.handleAudienceSaved);
    }

    handleAudienceSaved(event) {
        const redirectUrl = event.detail?.url;

        if (typeof redirectUrl === 'string' && redirectUrl !== '') {
            window.location.href = redirectUrl;
        }
    }
}
