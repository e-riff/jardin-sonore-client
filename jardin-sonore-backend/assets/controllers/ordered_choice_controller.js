import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'choice', 'search', 'list', 'order'];
    static values = {
        moveUpLabel: String,
        moveDownLabel: String,
        removeLabel: String,
    };

    connect() {
        this.sync();
    }

    choose(event) {
        event.preventDefault();
        const option = this.sourceOption(event.currentTarget.dataset.choiceValue);
        if (!option || option.selected) {
            return;
        }

        option.selected = true;
        this.sourceTarget.append(option);
        this.sync();
        this.dispatch('selected');
    }

    filter() {
        const query = this.searchTarget.value.trim().toLocaleLowerCase('fr');
        this.choiceTargets.forEach((choice) => {
            choice.hidden = !choice.textContent.toLocaleLowerCase('fr').includes(query);
        });
    }

    remove(event) {
        event.preventDefault();

        const item = event.currentTarget.closest('[data-uuid]');
        const option = item ? this.sourceOption(item.dataset.uuid) : null;
        if (!item || !option) {
            return;
        }

        option.selected = false;
        item.remove();
        this.syncChoices();
        this.syncOrder();
    }

    moveUp(event) {
        event.preventDefault();
        const item = event.currentTarget.closest('[data-uuid]');
        const previousItem = item?.previousElementSibling;
        if (!item || !previousItem) {
            return;
        }

        this.listTarget.insertBefore(item, previousItem);
        this.syncSourceOrder();
        this.syncOrder();
    }

    moveDown(event) {
        event.preventDefault();
        const item = event.currentTarget.closest('[data-uuid]');
        const nextItem = item?.nextElementSibling;
        if (!item || !nextItem) {
            return;
        }

        this.listTarget.insertBefore(nextItem, item);
        this.syncSourceOrder();
        this.syncOrder();
    }

    sync() {
        this.listTarget.replaceChildren();
        this.orderedSourceOptions().forEach((option) => this.listTarget.append(this.item(option)));
        this.syncChoices();
        this.syncOrder();
    }

    orderedSourceOptions() {
        const selectedOptions = Array.from(this.sourceTarget.selectedOptions);
        const selectedOptionsByValue = new Map(selectedOptions.map((option) => [option.value, option]));
        const orderedOptions = this.orderTarget.value
            .split(',')
            .map((uuid) => selectedOptionsByValue.get(uuid))
            .filter((option) => option instanceof HTMLOptionElement);

        selectedOptions.forEach((option) => {
            if (!orderedOptions.includes(option)) {
                orderedOptions.push(option);
            }
        });

        return orderedOptions;
    }

    syncSourceOrder() {
        Array.from(this.listTarget.children).forEach((item) => {
            const option = this.sourceOption(item.dataset.uuid);
            if (option) {
                this.sourceTarget.append(option);
            }
        });
    }

    syncOrder() {
        this.orderTarget.value = Array.from(this.listTarget.children)
            .map((item) => item.dataset.uuid)
            .join(',');
    }

    syncChoices() {
        this.choiceTargets.forEach((choice) => {
            choice.disabled = Boolean(this.sourceOption(choice.dataset.choiceValue)?.selected);
        });
    }

    sourceOption(value) {
        return Array.from(this.sourceTarget.options).find((option) => option.value === value);
    }

    item(option) {
        const item = document.createElement('li');
        item.dataset.uuid = option.value;
        item.className = 'ordered-choice__item';

        const title = document.createElement('span');
        title.className = 'ordered-choice__title';
        title.textContent = option.text;
        item.append(title);

        const actions = document.createElement('div');
        actions.className = 'ordered-choice__actions';
        actions.append(
            this.button('↑', this.moveUpLabelValue, this.moveUp.bind(this)),
            this.button('↓', this.moveDownLabelValue, this.moveDown.bind(this)),
            this.button(this.removeLabelValue, this.removeLabelValue, this.remove.bind(this)),
        );
        const imageUrl = this.choiceTargets.find((choice) => choice.dataset.choiceValue === option.value)?.dataset.imageUrl;
        if (imageUrl) {
            const image = document.createElement('img');
            image.src = imageUrl;
            image.alt = '';
            image.className = 'ordered-choice__thumbnail';
            actions.append(image);
        }
        item.append(actions);

        return item;
    }

    button(label, accessibleLabel, action) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'internal-button internal-button--secondary internal-button--compact';
        button.textContent = label;
        button.setAttribute('aria-label', accessibleLabel);
        button.addEventListener('click', action);

        return button;
    }
}
