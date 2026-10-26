import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['hex', 'picker'];

    syncHex() {
        this.hexTarget.value = this.pickerTarget.value;
    }

    syncPicker() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.hexTarget.value)) {
            this.pickerTarget.value = this.hexTarget.value;

            return;
        }

        const rgbMatch = this.hexTarget.value.match(/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i);
        if (rgbMatch) {
            const channels = rgbMatch.slice(1).map(Number);

            if (channels.every((channel) => channel <= 255)) {
                const hex = `#${channels.map((channel) => channel.toString(16).padStart(2, '0')).join('')}`;
                this.pickerTarget.value = hex;
            }
        }
    }
}
