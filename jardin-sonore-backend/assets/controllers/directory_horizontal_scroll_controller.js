import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['viewport'];

    connect() {
        this.dragStartX = 0;
        this.initialScrollLeft = 0;
        this.isDragging = false;
        this.shouldSuppressClick = false;

        this.startDragging = this.startDragging.bind(this);
        this.moveDragging = this.moveDragging.bind(this);
        this.stopDragging = this.stopDragging.bind(this);
        this.suppressClick = this.suppressClick.bind(this);

        this.viewportTarget.addEventListener('pointerdown', this.startDragging);
        this.viewportTarget.addEventListener('click', this.suppressClick, true);
    }

    disconnect() {
        this.viewportTarget.removeEventListener('pointerdown', this.startDragging);
        this.viewportTarget.removeEventListener('click', this.suppressClick, true);
        this.removeWindowListeners();
    }

    scrollPrevious() {
        this.viewportTarget.scrollBy({ left: -this.viewportTarget.clientWidth * 0.75, behavior: 'smooth' });
    }

    scrollNext() {
        this.viewportTarget.scrollBy({ left: this.viewportTarget.clientWidth * 0.75, behavior: 'smooth' });
    }

    startDragging(event) {
        if (event.pointerType !== 'mouse' || 0 !== event.button || this.viewportTarget.scrollWidth <= this.viewportTarget.clientWidth) {
            return;
        }

        this.dragStartX = event.clientX;
        this.initialScrollLeft = this.viewportTarget.scrollLeft;
        this.isDragging = false;
        window.addEventListener('pointermove', this.moveDragging);
        window.addEventListener('pointerup', this.stopDragging);
        window.addEventListener('pointercancel', this.stopDragging);
    }

    moveDragging(event) {
        const distance = event.clientX - this.dragStartX;
        if (!this.isDragging && Math.abs(distance) < 5) {
            return;
        }

        this.isDragging = true;
        this.viewportTarget.classList.add('is-dragging');
        this.viewportTarget.scrollLeft = this.initialScrollLeft - distance;
        event.preventDefault();
    }

    stopDragging() {
        this.removeWindowListeners();
        this.viewportTarget.classList.remove('is-dragging');

        if (this.isDragging) {
            this.shouldSuppressClick = true;
            window.setTimeout(() => {
                this.shouldSuppressClick = false;
            }, 0);
        }

        this.isDragging = false;
    }

    suppressClick(event) {
        if (!this.shouldSuppressClick) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.shouldSuppressClick = false;
    }

    removeWindowListeners() {
        window.removeEventListener('pointermove', this.moveDragging);
        window.removeEventListener('pointerup', this.stopDragging);
        window.removeEventListener('pointercancel', this.stopDragging);
    }
}
