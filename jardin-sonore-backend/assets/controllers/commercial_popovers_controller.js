import { Controller } from '@hotwired/stimulus';

const popupSelector = [
    '.commercial-dossier__status',
    '.commercial-dossier__intervention-menu',
    '.commercial-dossier__task-menu',
    '.commercial-dossier__document-action',
    '.commercial-timeline__edit',
].join(', ');

export default class extends Controller {
    connect() {
        this.onToggle = this.onToggle.bind(this);
        this.onPointerDown = this.onPointerDown.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);

        this.element.addEventListener('toggle', this.onToggle, true);
        document.addEventListener('pointerdown', this.onPointerDown);
        document.addEventListener('keydown', this.onKeyDown);
    }

    disconnect() {
        this.element.removeEventListener('toggle', this.onToggle, true);
        document.removeEventListener('pointerdown', this.onPointerDown);
        document.removeEventListener('keydown', this.onKeyDown);
    }

    onToggle(event) {
        const openedPopup = event.target;
        if (!(openedPopup instanceof HTMLDetailsElement) || !openedPopup.matches(popupSelector) || !openedPopup.open) {
            return;
        }

        this.popups().forEach((popup) => {
            if (popup !== openedPopup) {
                popup.open = false;
            }
        });
    }

    onPointerDown(event) {
        const clickedPopup = event.target instanceof Element ? event.target.closest(popupSelector) : null;
        if (clickedPopup && this.element.contains(clickedPopup)) {
            return;
        }

        this.closeAll();
    }

    onKeyDown(event) {
        if (event.key !== 'Escape') {
            return;
        }

        const openPopup = Array.from(this.popups()).find((popup) => popup.open);
        if (openPopup) {
            this.closeAll();
            openPopup.querySelector('summary')?.focus();
        }
    }

    closeAll() {
        this.popups().forEach((popup) => {
            popup.open = false;
        });
    }

    popups() {
        return this.element.querySelectorAll(popupSelector);
    }
}
