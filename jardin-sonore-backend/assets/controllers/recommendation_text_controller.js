import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'editor', 'output', 'toolbar'];
    static values = { limit: Number, counter: String, linkPrompt: String, linkInvalid: String, selectionRequired: String };

    connect() {
        this.editorTarget.hidden = false;
        this.toolbarTarget.hidden = false;
        this.inputTarget.hidden = true;
        this.inputTarget.required = false;
        this.update();
    }

    disconnect() {
        this.range = null;
    }

    keepSelection(event) {
        event.preventDefault();
    }

    rememberSelection() {
        const selection = window.getSelection();
        if (selection?.rangeCount && this.editorTarget.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            this.range = selection.getRangeAt(0).cloneRange();
        }
    }

    addLink() {
        this.rememberSelection();
        const range = this.range;
        if (!range || range.collapsed || !this.editorTarget.contains(range.commonAncestorContainer)) {
            window.alert(this.selectionRequiredValue);
            return;
        }
        const url = window.prompt(this.linkPromptValue, 'https://');
        if (url === null) {
            return;
        }
        let safeUrl = false;
        try {
            const parsed = new URL(url.trim());
            safeUrl = ['http:', 'https:'].includes(parsed.protocol) && !!parsed.hostname && !parsed.username && !parsed.password;
        } catch { /* The translated validation below handles invalid URLs. */ }
        if (!safeUrl) {
            window.alert(this.linkInvalidValue);
            return;
        }
        const parentLink = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
            ? range.commonAncestorContainer.closest('a')
            : range.commonAncestorContainer.parentElement?.closest('a');
        if (parentLink && this.editorTarget.contains(parentLink)) {
            parentLink.href = url.trim();
        } else {
            const anchor = document.createElement('a');
            anchor.href = url.trim();
            const content = range.extractContents();
            content.querySelectorAll('a').forEach((link) => link.replaceWith(...link.childNodes));
            anchor.append(content);
            range.insertNode(anchor);
        }
        this.editorTarget.focus();
        this.range = null;
        this.sync();
    }

    paste(event) {
        event.preventDefault();
        this.insertText(event.clipboardData.getData('text/plain'));
    }

    lineBreak(event) {
        event.preventDefault();
        this.insertText('\n');
    }

    insertText(value) {
        const selection = window.getSelection();
        if (!selection?.rangeCount || !this.editorTarget.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            return;
        }
        const range = selection.getRangeAt(0);
        const fragment = document.createDocumentFragment();
        value.replace(/\r\n?/g, '\n').split('\n').forEach((line, index) => {
            if (index > 0) fragment.append(document.createElement('br'));
            fragment.append(document.createTextNode(line));
        });
        const lastNode = fragment.lastChild;
        range.deleteContents();
        range.insertNode(fragment);
        range.setStartAfter(lastNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        this.sync();
    }

    sync() {
        this.inputTarget.value = this.editorTarget.innerHTML;
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.update();
    }

    sourceChanged() {
        this.update();
    }

    visibleText(node) {
        if (node.nodeType === Node.TEXT_NODE) return node.textContent;
        if (node.nodeName === 'BR') return '\n';
        return [...node.childNodes].map((child) => this.visibleText(child)).join('');
    }

    update() {
        const remaining = this.limitValue - Array.from(this.visibleText(this.editorTarget)).length;
        this.outputTarget.textContent = this.counterValue.replace('%remaining%', String(remaining));
        this.outputTarget.classList.toggle('character-counter--exceeded', remaining < 0);
        this.editorTarget.setAttribute('aria-invalid', String(remaining < 0));
    }
}
