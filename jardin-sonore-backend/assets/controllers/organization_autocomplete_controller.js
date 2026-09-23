import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = { url: String };

    connect() {
        this.tomSelect = new TomSelect(this.element, {
            valueField: 'id',
            labelField: 'label',
            searchField: 'label',
            maxItems: 1,
            preload: false,
            load: (query, callback) => {
                fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((payload) => callback(payload.items))
                    .catch(() => callback());
            },
        });
    }

    disconnect() {
        this.tomSelect?.destroy();
    }
}
