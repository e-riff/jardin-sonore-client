import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['body', 'list'];

    connect() {
        this.bodyTarget.hidden = true;
        const instructions = this.bodyTarget.value.split('\n').map((value) => value.trim()).filter(Boolean);
        this.render(instructions.length ? instructions : ['']);
    }

    add() {
        this.render([...this.values(), '']);
        this.listTarget.lastElementChild.querySelector('input').focus();
    }

    sync(event) {
        if (event.key === 'Enter' && (event.ctrlKey || event.shiftKey || event.altKey)) {
            event.preventDefault();
            this.add();
        }
        this.bodyTarget.value = this.values().join('\n');
    }

    render(values) {
        this.listTarget.replaceChildren(...values.map((value) => {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = value;
            input.addEventListener('input', this.sync.bind(this));
            input.addEventListener('blur', this.sync.bind(this));
            input.addEventListener('keydown', this.sync.bind(this));
            const row = document.createElement('div');
            row.append(input);
            return row;
        }));
        this.bodyTarget.value = this.values().join('\n');
    }

    values() {
        return Array.from(this.listTarget.querySelectorAll('input')).map((input) => input.value.trim()).filter(Boolean);
    }
}
