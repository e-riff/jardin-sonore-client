import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["collection", "importSource", "preview"];
    static values = {
        index: Number,
    };

    connect() {
        if (!this.hasIndexValue) {
            this.indexValue = this.collectionTarget.children.length;
        }

        this.syncRows();
    }

    import(event) {
        event.preventDefault();

        const source = this.importSourceTarget.value;
        if (!source.trim()) {
            return;
        }

        source.split(/\r\n|\n|\r/).forEach((line) => {
            if (!line.trim()) {
                this.appendBlock({ kind: "break" });
                return;
            }

            this.appendBlock({
                kind: "line",
                text: line.trim(),
            });
        });

        this.importSourceTarget.value = "";
        this.syncRows();
    }

    addLine(event) {
        event.preventDefault();
        this.appendBlock({ kind: "line" }, event.currentTarget.dataset.position ?? "end");
    }

    kindChanged() {
        this.syncRows();
    }

    refreshPreview() {
        this.renderPreview();
    }

    removeBlock(event) {
        event.preventDefault();
        event.currentTarget.closest("[data-repertoire-block-row]")?.remove();
        this.syncRows();
    }

    moveUp(event) {
        event.preventDefault();
        const row = event.currentTarget.closest("[data-repertoire-block-row]");
        if (!row?.previousElementSibling) {
            return;
        }

        row.parentNode.insertBefore(row, row.previousElementSibling);
        this.syncRows();
    }

    moveDown(event) {
        event.preventDefault();
        const row = event.currentTarget.closest("[data-repertoire-block-row]");
        if (!row?.nextElementSibling) {
            return;
        }

        row.parentNode.insertBefore(row.nextElementSibling, row);
        this.syncRows();
    }

    appendBlock(payload, position = "end") {
        const prototype = this.collectionTarget.dataset.prototype;
        if (!prototype) {
            return;
        }

        const html = prototype.replace(/__name__/g, String(this.indexValue));
        this.indexValue += 1;

        const template = document.createElement("template");
        template.innerHTML = html.trim();

        const row = template.content.firstElementChild;
        if (!row) {
            return;
        }

        if (position === "start") {
            this.collectionTarget.prepend(row);
        } else {
            this.collectionTarget.appendChild(row);
        }
        this.applyPayload(row, payload);
        this.syncRows();
    }

    applyPayload(row, payload) {
        const kindField = row.querySelector('select[name$="[kind]"]');
        if (kindField && payload.kind) {
            kindField.value = payload.kind;
        }

        const textField = row.querySelector('textarea[name$="[text]"]');
        if (textField && Object.hasOwn(payload, "text")) {
            textField.value = payload.text ?? "";
        }

        const gestureField = row.querySelector('textarea[name$="[gesture]"]');
        if (gestureField && Object.hasOwn(payload, "gesture")) {
            gestureField.value = payload.gesture ?? "";
        }
    }

    syncRows() {
        this.collectionTarget.querySelectorAll("[data-repertoire-block-row]").forEach((row, index) => {
            this.syncRowIndex(row, index);
            this.updateRowVisibility(row);
        });

        this.markSectionGroups();
        this.renderPreview();
    }

    syncRowIndex(row, index) {
        row.querySelectorAll("[name], [id], label[for]").forEach((field) => {
            if (field.name) {
                field.name = field.name.replace(
                    /(\[contentBlocks\]\[)\d+(\])/,
                    `$1${index}$2`,
                );
            }

            if (field.id) {
                field.id = field.id.replace(
                    /(_contentBlocks_)\d+(_)/,
                    `$1${index}$2`,
                );
            }

            if (field.htmlFor) {
                field.htmlFor = field.htmlFor.replace(
                    /(_contentBlocks_)\d+(_)/,
                    `$1${index}$2`,
                );
            }
        });
    }

    updateRowVisibility(row) {
        const kindField = row.querySelector('select[name$="[kind]"]');
        if (kindField && kindField.value !== "line" && kindField.value !== "break") {
            kindField.value = "line";
        }

        const kind = kindField?.value ?? "line";
        const textField = row.querySelector('textarea[name$="[text]"]');
        const gestureField = row.querySelector('textarea[name$="[gesture]"]');
        const textLabel = row.querySelector('[data-repertoire-block-label="text"]');

        this.toggleField(row, "text", true);
        this.toggleField(row, "gesture", kind === "line");

        if (kind === "break") {
            if (gestureField) {
                gestureField.value = "";
            }
        }

        if (textLabel) {
            textLabel.textContent = kind === "break"
                ? textLabel.dataset.breakLabel ?? textLabel.textContent
                : textLabel.dataset.lineLabel ?? textLabel.textContent;
        }

        if (textField) {
            textField.placeholder = kind === "break"
                ? "Refrain, couplet 2, transition..."
                : "";
        }

        row.dataset.blockKind = kind;
    }

    toggleField(row, fieldName, shouldShow) {
        const field = row.querySelector(`[data-repertoire-block-field="${fieldName}"]`);
        if (!field) {
            return;
        }

        field.hidden = !shouldShow;
    }

    markSectionGroups() {
        const rows = Array.from(this.collectionTarget.querySelectorAll("[data-repertoire-block-row]"));
        let sectionIsOpen = false;

        rows.forEach((row) => {
            const kind = row.dataset.blockKind ?? "line";
            const text = row.querySelector('textarea[name$="[text]"]')?.value.trim() ?? "";

            row.dataset.sectionLead = kind === "break" && text !== "" ? "true" : "false";
            row.dataset.sectionGrouped = "false";

            if (kind === "break") {
                sectionIsOpen = text !== "";
                return;
            }

            if (sectionIsOpen) {
                row.dataset.sectionGrouped = "true";
            }
        });
    }

    renderPreview() {
        if (!this.hasPreviewTarget) {
            return;
        }

        const fragments = [];

        this.collectionTarget.querySelectorAll("[data-repertoire-block-row]").forEach((row) => {
            const kind = row.dataset.blockKind ?? "line";
            const text = row.querySelector('textarea[name$="[text]"]')?.value.trim() ?? "";
            const gesture = row.querySelector('textarea[name$="[gesture]"]')?.value.trim() ?? "";

            if (kind === "break") {
                if (text) {
                    fragments.push('<div class="repertoire-block-preview__separator"></div>');
                    fragments.push(`<div class="repertoire-block-preview__section">${this.escapeHtml(text)}</div>`);
                    return;
                }

                fragments.push('<div class="repertoire-block-preview__separator"></div>');
                return;
            }

            if (!text) {
                return;
            }

            const gestureHtml = gesture ? ` <em>— ${this.escapeHtml(gesture)}</em>` : "";
            fragments.push(`<div class="repertoire-block-preview__line"><span>${this.escapeHtml(text)}</span>${gestureHtml}</div>`);
        });

        this.previewTarget.innerHTML = fragments.join("");
    }

    escapeHtml(value) {
        return value
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;");
    }
}
