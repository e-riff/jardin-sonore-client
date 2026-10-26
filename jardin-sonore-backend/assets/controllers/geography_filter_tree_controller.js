import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['department', 'region', 'selection'];

    connect() {
        this.updateRegionStates();
    }

    regionTargetConnected(regionElement) {
        regionElement.indeterminate = regionElement.dataset.partial === 'true';
    }

    toggleRegion(event) {
        const regionCode = event.currentTarget.dataset.regionCode;
        this.departmentTargets
            .filter((departmentElement) => departmentElement.dataset.regionCode === regionCode)
            .forEach((departmentElement) => {
                departmentElement.checked = event.currentTarget.checked;
            });

        this.updateRegionStates();
        this.updateSelection();
    }

    toggleDepartment() {
        this.updateRegionStates();
        this.updateSelection();
    }

    updateRegionStates() {
        this.regionTargets.forEach((regionElement) => {
            const regionDepartments = this.departmentTargets
                .filter((departmentElement) => departmentElement.dataset.regionCode === regionElement.dataset.regionCode);
            const selectedCount = regionDepartments.filter((departmentElement) => departmentElement.checked).length;

            regionElement.checked = regionDepartments.length > 0 && selectedCount === regionDepartments.length;
            regionElement.indeterminate = selectedCount > 0 && selectedCount < regionDepartments.length;
            regionElement.dataset.partial = regionElement.indeterminate ? 'true' : 'false';
        });
    }

    updateSelection() {
        const selectedCodes = this.departmentTargets
            .filter((departmentElement) => departmentElement.checked)
            .map((departmentElement) => departmentElement.value);

        this.selectionTarget.value = selectedCodes.length > 0 ? selectedCodes.join(',') : '*';
        this.selectionTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
