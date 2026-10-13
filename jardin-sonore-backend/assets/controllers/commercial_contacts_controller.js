import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.desktop = window.matchMedia('(min-width: 761px)');
        this.onBreakpointChange = (event) => {
            this.element.open = event.matches;
        };
        this.element.open = this.desktop.matches;
        this.desktop.addEventListener('change', this.onBreakpointChange);
    }

    disconnect() {
        this.desktop.removeEventListener('change', this.onBreakpointChange);
    }
}
