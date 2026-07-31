import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static targets = ['input'];

    async connect() {
        this.liveComponent = await getComponent(this.element.closest('[data-controller~="live"]'));
        this.liveComponent.on('render:finished', () => {
            if (!this.focusNextInstruction) {
                return;
            }

            this.focusNextInstruction = false;
            this.liveComponent.element.querySelector('[data-instruction-shortcut-target~="input"]')?.focus();
        });
    }

    async saveInstruction() {
        if (this.isAddingInstruction) {
            return;
        }

        await this.liveComponent.action('saveInstruction');
    }

    async addNextInstruction(event) {
        if (event.key !== 'Enter' || (!event.ctrlKey && !event.shiftKey && !event.altKey)) {
            return;
        }

        event.preventDefault();
        this.isAddingInstruction = true;
        this.focusNextInstruction = true;

        try {
            await this.liveComponent.action('saveAndAddInstruction');
        } finally {
            this.isAddingInstruction = false;
        }
    }
}
