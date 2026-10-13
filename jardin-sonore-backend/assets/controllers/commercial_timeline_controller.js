import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['excerpt', 'toggle'];
    static values = {
        expanded: Boolean,
        moreLabel: String,
        lessLabel: String,
    };

    connect() {
        this.onResize = () => this.refresh();
        window.addEventListener('resize', this.onResize);
        this.frame = requestAnimationFrame(() => this.refresh());
    }

    disconnect() {
        window.removeEventListener('resize', this.onResize);
        cancelAnimationFrame(this.frame);
    }

    toggle() {
        this.expandedValue = !this.expandedValue;
        this.refresh();
    }

    refresh() {
        if (!this.hasExcerptTarget || !this.hasToggleTarget) {
            return;
        }

        this.excerptTarget.classList.add('is-clamped');
        const overflows = this.excerptTarget.scrollHeight > this.excerptTarget.clientHeight + 1;
        this.toggleTarget.hidden = !overflows;
        this.toggleTarget.textContent = this.expandedValue ? this.lessLabelValue : this.moreLabelValue;
        this.toggleTarget.setAttribute('aria-expanded', this.expandedValue ? 'true' : 'false');

        if (!overflows || this.expandedValue) {
            this.excerptTarget.classList.remove('is-clamped');
        }
    }
}
