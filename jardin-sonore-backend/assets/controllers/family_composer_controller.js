import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['choice', 'ordered'];
    static values = { order: Array };

    connect() {
        const previousOrder = Array.from(this.orderedTarget.children, (input) => input.value);
        this.orderedTarget.replaceChildren();
        this.choiceTargets.forEach((choice) => choice.removeAttribute('name'));
        const restored = new Set();
        (previousOrder.length ? previousOrder : this.orderValue).forEach((value) => {
            const choice = this.choiceTargets.find((candidate) => candidate.value === value && candidate.checked);
            if (choice && !restored.has(value)) {
                this.addChoice(choice);
                restored.add(value);
            }
        });
        this.choiceTargets.forEach((choice) => {
            if (choice.checked && !restored.has(choice.value)) this.addChoice(choice);
        });
    }

    change(event) {
        const choice = event.currentTarget;
        if (choice.checked) {
            this.choiceTargets.forEach((other) => {
                if (other !== choice && other.dataset.familyComposerGroupParam === choice.dataset.familyComposerGroupParam && other.checked) {
                    other.checked = false;
                    this.removeChoice(other);
                }
            });
            this.addChoice(choice);
        } else {
            this.removeChoice(choice);
        }
    }

    addChoice(choice) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selection[]';
        input.value = choice.value;
        this.orderedTarget.append(input);
    }

    removeChoice(choice) {
        Array.from(this.orderedTarget.children).find((input) => input.value === choice.value)?.remove();
    }
}
