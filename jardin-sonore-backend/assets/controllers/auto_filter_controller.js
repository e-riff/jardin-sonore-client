import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'results'];

    connect() {
        this.delay = null;
        this.request = null;
    }

    disconnect() {
        clearTimeout(this.delay);
        this.request?.abort();
    }

    schedule() {
        clearTimeout(this.delay);
        this.delay = setTimeout(() => this.refresh(), 350);
    }

    change(event) {
        if (event.target.matches('select')) this.refresh();
    }

    submit(event) {
        event.preventDefault();
        this.refresh();
    }

    async refresh() {
        clearTimeout(this.delay);
        this.request?.abort();
        const request = new AbortController();
        this.request = request;

        const url = new URL(this.formTarget.action || window.location.href);
        url.search = new URLSearchParams(new FormData(this.formTarget)).toString();
        this.resultsTarget.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html' },
                signal: request.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (request.signal.aborted) return;
            const results = page.querySelector('[data-auto-filter-target="results"]');
            if (!results) throw new Error('Missing filtered results');

            this.resultsTarget.innerHTML = results.innerHTML;
            window.history.replaceState(window.history.state, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            if (this.request === request) this.resultsTarget.removeAttribute('aria-busy');
        }
    }
}
