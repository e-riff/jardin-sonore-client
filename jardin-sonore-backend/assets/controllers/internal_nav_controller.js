import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'panel', 'shell'];

    connect() {
        this.mediaQuery = window.matchMedia('(max-width: 600px)');
        this.handleViewportChange = this.handleViewportChange.bind(this);

        this.handleViewportChange();

        if (typeof this.mediaQuery.addEventListener === 'function') {
            this.mediaQuery.addEventListener('change', this.handleViewportChange);

            return;
        }

        this.mediaQuery.addListener(this.handleViewportChange);
    }

    disconnect() {
        if (typeof this.mediaQuery?.removeEventListener === 'function') {
            this.mediaQuery.removeEventListener('change', this.handleViewportChange);

            return;
        }

        this.mediaQuery?.removeListener(this.handleViewportChange);
    }

    toggle() {
        if (!this.mediaQuery.matches) {
            return;
        }

        this.setExpanded(this.shellTarget.classList.contains('is-collapsed'));
    }

    handleViewportChange() {
        this.setExpanded(!this.mediaQuery.matches);
    }

    setExpanded(expanded) {
        this.shellTarget.classList.toggle('is-open', expanded);
        this.shellTarget.classList.toggle('is-collapsed', !expanded);
        this.buttonTarget.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}
