import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'panel', 'shell'];

    connect() {
        this.mediaQuery = window.matchMedia('(max-width: 760px)');
        this.handleViewportChange = this.handleViewportChange.bind(this);

        this.handleViewportChange();
        this.syncDesktopGroups();
        this.syncGroupPanels();

        if (typeof this.mediaQuery.addEventListener === 'function') {
            this.mediaQuery.addEventListener('change', this.handleViewportChange);

            return;
        }

        this.mediaQuery.addListener(this.handleViewportChange);
    }

    disconnect() {
        document.body.classList.remove('internal-nav-open');

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

        this.setExpanded(!this.shellTarget.classList.contains('is-open'));
    }

    selectGroup(event) {
        if (this.mediaQuery.matches) {
            return;
        }

        const selectedKey = event.currentTarget.dataset.navGroupKey;

        if (!selectedKey) {
            return;
        }

        this.setDesktopGroup(selectedKey);
    }

    close(event) {
        if (event && event.currentTarget === this.shellTarget && event.target !== this.shellTarget) {
            return;
        }

        this.setExpanded(false);
    }

    toggleGroup(event) {
        if (!this.mediaQuery.matches) {
            return;
        }

        const group = event.currentTarget.closest('.internal-mobile-nav__group');

        if (!group) {
            return;
        }

        const shouldExpand = !group.classList.contains('is-open');

        for (const otherGroup of this.element.querySelectorAll('.internal-mobile-nav__group')) {
            if (otherGroup === group) {
                continue;
            }

            this.setGroupExpanded(otherGroup, false);
        }

        this.setGroupExpanded(group, shouldExpand);
    }

    handleViewportChange() {
        this.setExpanded(false);
        this.syncDesktopGroups();
        this.syncGroupPanels();
    }

    setExpanded(expanded) {
        if (!this.hasShellTarget || !this.hasButtonTarget) {
            return;
        }

        const isExpanded = this.mediaQuery.matches && expanded;

        this.shellTarget.classList.toggle('is-open', isExpanded);
        this.buttonTarget.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        document.body.classList.toggle('internal-nav-open', isExpanded);
    }

    setGroupExpanded(group, expanded) {
        const groupButton = group.querySelector('.internal-mobile-nav__toggle');
        const groupPanel = group.querySelector('.internal-mobile-nav__links');

        if (!groupButton || !groupPanel) {
            return;
        }

        group.classList.toggle('is-open', expanded);
        groupButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        groupPanel.style.maxHeight = expanded ? `${groupPanel.scrollHeight}px` : '0px';
    }

    syncGroupPanels() {
        for (const group of this.element.querySelectorAll('.internal-mobile-nav__group')) {
            const groupPanel = group.querySelector('.internal-mobile-nav__links');

            if (!groupPanel) {
                continue;
            }

            groupPanel.style.maxHeight = group.classList.contains('is-open') ? `${groupPanel.scrollHeight}px` : '0px';
        }
    }

    syncDesktopGroups() {
        if (this.mediaQuery.matches) {
            return;
        }

        const activeButton = this.element.querySelector('.internal-primary-nav__link.is-active');
        const activeKey = activeButton?.dataset.navGroupKey;

        if (activeKey) {
            this.setDesktopGroup(activeKey);
        }
    }

    setDesktopGroup(selectedKey) {
        for (const button of this.element.querySelectorAll('.internal-primary-nav__link')) {
            const isActive = button.dataset.navGroupKey === selectedKey;

            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            if (isActive) {
                button.setAttribute('aria-current', 'page');
            } else {
                button.removeAttribute('aria-current');
            }
        }

        for (const group of this.element.querySelectorAll('.internal-subnav__group')) {
            group.classList.toggle('is-active', group.dataset.navGroupKey === selectedKey);
        }
    }
}
