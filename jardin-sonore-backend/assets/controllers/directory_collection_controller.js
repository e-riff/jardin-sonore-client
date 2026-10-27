import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'removeButtonTemplate'];
    static values = { index: Number, prototype: String };

    add() {
        const entry = this.prototypeValue.replaceAll('__name__', String(this.indexValue));
        this.indexValue += 1;
        const wrapper = document.createElement('div');
        wrapper.classList.add('directory-form__collection-entry');
        wrapper.dataset.collectionEntry = '';
        wrapper.innerHTML = entry;

        wrapper.append(this.removeButtonTemplateTarget.content.firstElementChild.cloneNode(true));
        this.listTarget.append(wrapper);
    }

    remove(event) {
        event.currentTarget.closest('[data-collection-entry]')?.remove();
    }
}
