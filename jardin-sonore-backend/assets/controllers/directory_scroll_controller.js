import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['detail'];

    connect() {
        this.wasSelected = this.element.querySelector('.directory-catalog__layout')?.classList.contains('has-selection') ?? false;
        this.observer = new MutationObserver(() => this.handleSelectionChange());
        this.observer.observe(this.element, { attributes: true, childList: true, subtree: true, attributeFilter: ['class'] });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    handleSelectionChange() {
        const layout = this.element.querySelector('.directory-catalog__layout');
        const isSelected = layout?.classList.contains('has-selection') ?? false;
        if (!this.wasSelected && isSelected && window.matchMedia('(max-width: 760px)').matches && this.hasDetailTarget) {
            requestAnimationFrame(() => this.detailTarget.scrollIntoView({ block: 'start', behavior: 'smooth' }));
        }
        this.wasSelected = isSelected;
    }
}
