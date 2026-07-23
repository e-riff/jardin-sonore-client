import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'item', 'status'];
    static values = {
        url: String,
        csrfToken: String,
        errorMessage: String,
    };

    connect() {
        this.draggedItem = null;
        this.previousOrder = [];
        this.onDragStart = this.dragStart.bind(this);
        this.onDragOver = this.dragOver.bind(this);
        this.onDragEnd = this.dragEnd.bind(this);

        this.listTarget.addEventListener('dragstart', this.onDragStart);
        this.listTarget.addEventListener('dragover', this.onDragOver);
        this.listTarget.addEventListener('dragend', this.onDragEnd);
    }

    disconnect() {
        this.listTarget.removeEventListener('dragstart', this.onDragStart);
        this.listTarget.removeEventListener('dragover', this.onDragOver);
        this.listTarget.removeEventListener('dragend', this.onDragEnd);
    }

    dragStart(event) {
        const item = event.target.closest('[data-session-sequence-sorter-target="item"]');
        if (!item) {
            return;
        }

        this.draggedItem = item;
        this.previousOrder = this.sequenceUuids();
        event.dataTransfer.effectAllowed = 'move';
        item.classList.add('is-dragging');
    }

    dragOver(event) {
        if (!this.draggedItem) {
            return;
        }

        event.preventDefault();
        const targetItem = event.target.closest('[data-session-sequence-sorter-target="item"]');
        if (!targetItem || targetItem === this.draggedItem) {
            return;
        }

        const targetBounds = targetItem.getBoundingClientRect();
        const insertAfterTarget = event.clientY > targetBounds.top + targetBounds.height / 2;
        this.listTarget.insertBefore(this.draggedItem, insertAfterTarget ? targetItem.nextSibling : targetItem);
    }

    async dragEnd() {
        if (!this.draggedItem) {
            return;
        }

        this.draggedItem.classList.remove('is-dragging');
        this.draggedItem = null;

        if (!this.ordersMatch(this.previousOrder, this.sequenceUuids())) {
            await this.persist(this.previousOrder);
        }
    }

    async moveUp(event) {
        event.preventDefault();
        const item = event.target.closest('[data-session-sequence-sorter-target="item"]');
        const previousItem = item?.previousElementSibling;
        if (!item || !previousItem) {
            return;
        }

        const previousOrder = this.sequenceUuids();
        this.listTarget.insertBefore(item, previousItem);
        await this.persist(previousOrder);
    }

    async moveDown(event) {
        event.preventDefault();
        const item = event.target.closest('[data-session-sequence-sorter-target="item"]');
        const nextItem = item?.nextElementSibling;
        if (!item || !nextItem) {
            return;
        }

        const previousOrder = this.sequenceUuids();
        this.listTarget.insertBefore(nextItem, item);
        await this.persist(previousOrder);
    }

    async persist(previousOrder) {
        const formData = new FormData();
        formData.append('_token', this.csrfTokenValue);
        this.sequenceUuids().forEach((sequenceUuid) => formData.append('sequenceUuids[]', sequenceUuid));

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });

            if (!response.ok) {
                throw new Error('Unable to persist the sequence order.');
            }
        } catch (error) {
            this.restore(previousOrder);
            this.statusTarget.textContent = this.errorMessageValue;
        }
    }

    sequenceUuids() {
        return this.itemTargets.map((item) => item.dataset.sequenceUuid);
    }

    restore(sequenceUuids) {
        const itemsByUuid = new Map(this.itemTargets.map((item) => [item.dataset.sequenceUuid, item]));
        sequenceUuids.forEach((sequenceUuid) => this.listTarget.append(itemsByUuid.get(sequenceUuid)));
    }

    ordersMatch(firstOrder, secondOrder) {
        return firstOrder.length === secondOrder.length && firstOrder.every((uuid, index) => uuid === secondOrder[index]);
    }
}
