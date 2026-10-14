import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.desktopQuery = window.matchMedia('(min-width: 761px)');
        this.applyDefault = () => {
            if (!this.userToggled) {
                this.element.open = this.desktopQuery.matches;
            }
        };
        this.userToggled = false;
        this.onUserToggle = () => {
            this.userToggled = true;
        };
        this.applyDefault();
        this.desktopQuery.addEventListener('change', this.applyDefault);
        this.element.querySelector('summary')?.addEventListener('click', this.onUserToggle);
    }

    disconnect() {
        this.desktopQuery?.removeEventListener('change', this.applyDefault);
        this.element.querySelector('summary')?.removeEventListener('click', this.onUserToggle);
    }
}
