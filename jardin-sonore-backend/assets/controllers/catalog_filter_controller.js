import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'query'];

    filter() {
        const query = this.queryTarget.value.trim().toLocaleLowerCase();

        this.itemTargets.forEach((item) => {
            item.hidden = query !== '' && !item.dataset.catalogFilterSearchValue.toLocaleLowerCase().includes(query);
        });
    }
}
