import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['detail', 'toggle'];

    toggle() {
        const isExpanded = this.toggleTarget.getAttribute('aria-expanded') === 'true';
        this.element.classList.toggle('is-detail-collapsed', isExpanded);
        this.detailTarget.inert = isExpanded;
        this.detailTarget.setAttribute('aria-hidden', String(isExpanded));
        this.toggleTarget.setAttribute('aria-expanded', String(!isExpanded));
        this.toggleTarget.title = isExpanded ? this.toggleTarget.dataset.expandLabel : this.toggleTarget.dataset.collapseLabel;
        this.toggleTarget.setAttribute('aria-label', this.toggleTarget.title);
    }
}
