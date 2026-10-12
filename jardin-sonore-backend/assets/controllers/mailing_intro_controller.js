import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['collection'];
    static values = { linkPrompt: String, linkInvalid: String };

    static palette = {
        '#a64d43': '#A64D43',
        'rgb(166,77,67)': '#A64D43',
        '#692019': '#692019',
        'rgb(105,32,25)': '#692019',
        '#47664b': '#47664B',
        'rgb(71,102,75)': '#47664B',
    };

    connect() {
        this.rows().forEach((row) => this.initializeRow(row));
        this.reindex();
    }

    add(event) {
        const template = this.collectionTarget.dataset.prototype;
        if (!template) {
            return;
        }

        const index = this.rows().length;
        const container = document.createElement('div');
        container.innerHTML = template.replaceAll('__name__', String(index));
        const row = container.firstElementChild;
        if (!row) {
            return;
        }

        row.querySelector('input[name$="[id]"]').value = crypto.randomUUID();
        row.querySelector('select[name$="[kind]"]').value = event.currentTarget.dataset.kind;
        this.collectionTarget.append(row);
        event.currentTarget.closest('details').open = false;
        this.initializeRow(row);
        this.reindex();
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        row.querySelector('select[name$="[kind]"]').focus({ preventScroll: true });
        this.changed();
    }

    remove(event) {
        event.currentTarget.closest('[data-mailing-intro-block-row]').remove();
        this.reindex();
        this.changed();
    }

    moveUp(event) {
        const row = event.currentTarget.closest('[data-mailing-intro-block-row]');
        if (row.previousElementSibling) {
            row.previousElementSibling.before(row);
            this.reindex();
            this.changed();
        }
    }

    moveDown(event) {
        const row = event.currentTarget.closest('[data-mailing-intro-block-row]');
        if (row.nextElementSibling) {
            row.nextElementSibling.after(row);
            this.reindex();
            this.changed();
        }
    }

    kindChanged(event) {
        this.updateFields(event.currentTarget.closest('[data-mailing-intro-block-row]'));
        this.changed();
    }

    syncText(event) {
        const row = event.currentTarget.closest('[data-mailing-intro-block-row]');
        row.querySelector('[data-mailing-intro-textarea]').value = event.currentTarget.innerHTML;
        this.changed();
    }

    sourceChanged() {
        this.changed();
    }

    toggleHtml(event) {
        const row = event.currentTarget.closest('[data-mailing-intro-block-row]');
        const editorWrap = row.querySelector('[data-mailing-intro-editor-wrap]');
        const editor = row.querySelector('[data-mailing-intro-editor]');
        const textarea = row.querySelector('[data-mailing-intro-textarea]');

        if (textarea.hidden) {
            textarea.value = editor.innerHTML;
            editorWrap.hidden = true;
            textarea.hidden = false;
            event.currentTarget.textContent = event.currentTarget.dataset.visualLabel;
            textarea.focus();
            return;
        }

        editor.replaceChildren(this.sanitizeHtml(textarea.value));
        editorWrap.hidden = false;
        textarea.hidden = true;
        event.currentTarget.textContent = event.currentTarget.dataset.htmlLabel;
        editor.focus();
        this.syncText({ currentTarget: editor });
    }

    pasteRichText(event) {
        event.preventDefault();
        const html = event.clipboardData.getData('text/html');
        let fragment = html ? this.sanitizeHtml(html) : document.createDocumentFragment();
        if (!fragment.hasChildNodes()) {
            fragment = this.plainTextFragment(event.clipboardData.getData('text/plain'));
        }
        if (!fragment.hasChildNodes()) {
            return;
        }

        this.insertFragment(event.currentTarget, fragment);
    }

    insertLineBreak(event) {
        event.preventDefault();
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount) {
            return;
        }

        const range = selection.getRangeAt(0);
        if (!event.currentTarget.contains(range.commonAncestorContainer)) {
            return;
        }

        const breakElement = document.createElement('br');
        range.deleteContents();
        range.insertNode(breakElement);
        range.setStartAfter(breakElement);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        this.syncText({ currentTarget: event.currentTarget });
    }

    keepSelection(event) {
        event.preventDefault();
    }

    format(event) {
        const row = event.currentTarget.closest('[data-mailing-intro-block-row]');
        const editor = row.querySelector('[data-mailing-intro-editor]');
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0).cloneRange();
        if (!editor.contains(range.commonAncestorContainer)) {
            return;
        }

        const command = event.currentTarget.dataset.command;
        if (command === 'clear') {
            editor.focus();
            document.execCommand('removeFormat');
            document.execCommand('unlink');
            selection.collapseToEnd();
            this.syncText({ currentTarget: editor });
            return;
        }

        let wrapper;
        if (command === 'link') {
            const url = window.prompt(this.linkPromptValue, 'https://');
            if (url === null) {
                return;
            }
            if (!this.safeUrl(url.trim())) {
                window.alert(this.linkInvalidValue);
                return;
            }
            wrapper = document.createElement('a');
            wrapper.href = url.trim();
        } else if (command === 'color') {
            wrapper = document.createElement('span');
            wrapper.setAttribute('style', `color:${event.currentTarget.dataset.color};`);
        } else {
            wrapper = document.createElement(command === 'bold' ? 'strong' : 'em');
        }

        wrapper.append(range.extractContents());
        range.insertNode(wrapper);
        range.setStartAfter(wrapper);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        editor.focus();
        this.syncText({ currentTarget: editor });
    }

    initializeRow(row) {
        this.updateFields(row);
        const editorWrap = row.querySelector('[data-mailing-intro-editor-wrap]');
        const textarea = row.querySelector('[data-mailing-intro-textarea]');
        editorWrap.hidden = false;
        textarea.hidden = true;
        const viewSwitch = row.querySelector('[data-mailing-intro-view-switch]');
        if (viewSwitch) {
            viewSwitch.hidden = false;
        }
        const label = row.querySelector(`label[for="${textarea.id}"]`);
        if (label) {
            label.hidden = true;
        }
    }

    updateFields(row) {
        const kind = row.querySelector('select[name$="[kind]"]').value;
        row.querySelector('[data-mailing-intro-text-field]').hidden = kind === 'image';
        row.querySelector('[data-mailing-intro-image-field]').hidden = kind === 'paragraph';
        row.querySelector('[data-mailing-intro-side-field]').hidden = kind !== 'split';
    }

    reindex() {
        this.rows().forEach((row, index) => {
            row.querySelectorAll('[name], [id], label[for]').forEach((element) => {
                for (const attribute of ['name', 'id', 'for']) {
                    const value = element.getAttribute(attribute);
                    if (value) {
                        element.setAttribute(attribute, value.replace(/(introBlocks(?:\]\[|_))\d+/, `$1${index}`));
                    }
                }
            });
        });
    }

    rows() {
        return [...this.collectionTarget.children].filter((child) => child.matches('[data-mailing-intro-block-row]'));
    }

    changed() {
        this.element.dispatchEvent(new Event('input', { bubbles: true }));
    }

    plainTextFragment(value) {
        const fragment = document.createDocumentFragment();
        value.replaceAll('\r', '').split('\n').forEach((line, index) => {
            if (index > 0) {
                fragment.append(document.createElement('br'));
            }
            fragment.append(document.createTextNode(line));
        });

        return fragment;
    }

    sanitizeHtml(value) {
        const parsed = new DOMParser().parseFromString(value, 'text/html');
        const fragment = document.createDocumentFragment();
        parsed.body.childNodes.forEach((node) => this.appendSafeNode(node, fragment));
        while (fragment.lastChild?.nodeName === 'BR') {
            fragment.lastChild.remove();
        }

        return fragment;
    }

    appendSafeNode(node, parent) {
        if (node.nodeType === Node.TEXT_NODE) {
            parent.append(document.createTextNode(node.textContent));
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const tag = node.tagName.toLowerCase();
        if (['script', 'style', 'iframe', 'img', 'object', 'svg'].includes(tag)) {
            return;
        }
        if (tag === 'br') {
            parent.append(document.createElement('br'));
            return;
        }

        const isBlock = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'li', 'blockquote'].includes(tag);
        if (isBlock && parent.hasChildNodes() && parent.lastChild.nodeName !== 'BR') {
            parent.append(document.createElement('br'));
        }

        let destination = parent;
        if (['strong', 'b', 'em', 'i'].includes(tag)) {
            destination = document.createElement(['strong', 'b'].includes(tag) ? 'strong' : 'em');
        } else if (tag === 'a' && this.safeUrl(node.getAttribute('href'))) {
            destination = document.createElement('a');
            destination.setAttribute('href', node.getAttribute('href'));
        } else if (tag === 'span') {
            const key = node.style.color.replaceAll(' ', '').toLowerCase();
            const color = this.constructor.palette[key];
            if (color) {
                destination = document.createElement('span');
                destination.setAttribute('style', `color:${color};`);
            }
        }

        if (destination !== parent) {
            parent.append(destination);
        }
        if (tag === 'li') {
            destination.append(document.createTextNode('• '));
        }
        node.childNodes.forEach((child) => this.appendSafeNode(child, destination));
        if (isBlock && parent.lastChild?.nodeName !== 'BR') {
            parent.append(document.createElement('br'));
        }
    }

    safeUrl(value) {
        if (!/^https?:\/\//i.test(value || '')) {
            return false;
        }
        try {
            const url = new URL(value);
            return ['http:', 'https:'].includes(url.protocol) && !!url.hostname && !url.username && !url.password;
        } catch {
            return false;
        }
    }

    insertFragment(editor, fragment) {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || !editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            editor.append(fragment);
            this.syncText({ currentTarget: editor });
            return;
        }

        const range = selection.getRangeAt(0);
        const lastNode = fragment.lastChild;
        range.deleteContents();
        range.insertNode(fragment);
        range.setStartAfter(lastNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        this.syncText({ currentTarget: editor });
    }
}
