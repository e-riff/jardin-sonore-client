import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['editor', 'input'];

    static colors = {
        '#a64d43': '#A64D43',
        'rgb(166,77,67)': '#A64D43',
        '#692019': '#692019',
        'rgb(105,32,25)': '#692019',
        '#47664b': '#47664B',
        'rgb(71,102,75)': '#47664B',
    };

    connect() {
        this.inputTarget.value = this.inputTarget.value.replace(/\r\n?/g, '\n').replace(/\n{2,}/g, '\n');
        this.editorTarget.replaceChildren(this.sanitize(this.inputTarget.value));
        this.editorTarget.hidden = false;
        this.inputTarget.hidden = true;
    }

    keepSelection(event) {
        event.preventDefault();
    }

    format(event) {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0).cloneRange();
        if (!this.editorTarget.contains(range.commonAncestorContainer)) {
            return;
        }

        const command = event.currentTarget.dataset.command;
        let wrapper;
        if (command === 'bold') {
            wrapper = document.createElement('strong');
        } else if (command === 'italic') {
            wrapper = document.createElement('em');
        } else if (command === 'color') {
            const color = this.constructor.colors[event.currentTarget.dataset.color?.toLowerCase()];
            if (!color) {
                return;
            }
            wrapper = document.createElement('span');
            wrapper.style.color = color;
        } else {
            return;
        }

        wrapper.append(range.extractContents());
        range.insertNode(wrapper);
        range.setStartAfter(wrapper);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        this.sync();
        this.editorTarget.focus();
    }

    clearFormatting(event) {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0).cloneRange();
        if (!this.editorTarget.contains(range.commonAncestorContainer)) {
            return;
        }

        const selectedContent = range.extractContents();
        const plainContent = document.createDocumentFragment();
        [...selectedContent.childNodes].forEach((node) => this.appendUnformattedNode(node, plainContent));
        const insertedNodes = [...plainContent.childNodes];
        range.insertNode(plainContent);
        if (insertedNodes.length > 0) {
            range.setStartAfter(insertedNodes[insertedNodes.length - 1]);
        }
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        this.sync();
        this.editorTarget.focus();
    }

    appendUnformattedNode(node, parent) {
        if (node.nodeType === Node.TEXT_NODE) {
            parent.append(document.createTextNode(node.textContent));
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const tag = node.tagName.toLowerCase();
        if (tag === 'br') {
            parent.append(document.createElement('br'));
            return;
        }
        if (['div', 'p', 'li', 'blockquote'].includes(tag)) {
            const block = document.createElement(tag);
            parent.append(block);
            node.childNodes.forEach((child) => this.appendUnformattedNode(child, block));
            return;
        }

        node.childNodes.forEach((child) => this.appendUnformattedNode(child, parent));
    }

    paste(event) {
        event.preventDefault();
        const html = event.clipboardData.getData('text/html');
        const content = html ? this.sanitize(html) : this.plainText(event.clipboardData.getData('text/plain'));
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || !this.editorTarget.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            this.editorTarget.append(content);
        } else {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(content);
        }
        this.sync();
    }

    sync() {
        this.inputTarget.value = this.serialize(this.editorTarget);
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    sanitize(value) {
        const documentFragment = new DOMParser().parseFromString(value, 'text/html');
        const fragment = document.createDocumentFragment();
        [...documentFragment.body.childNodes].forEach((node) => this.appendSafeNode(node, fragment));

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
        if (['script', 'style', 'iframe', 'object', 'svg'].includes(tag)) {
            return;
        }
        if (tag === 'br') {
            parent.append(document.createElement('br'));
            return;
        }

        const isBlock = ['div', 'p', 'li', 'blockquote'].includes(tag);
        if (isBlock && parent.hasChildNodes() && parent.lastChild.nodeName !== 'BR') {
            parent.append(document.createElement('br'));
        }

        let destination = parent;
        if (['strong', 'b'].includes(tag)) {
            destination = document.createElement('strong');
        } else if (['em', 'i'].includes(tag)) {
            destination = document.createElement('em');
        } else if (tag === 'span') {
            const color = this.constructor.colors[node.style.color.replaceAll(' ', '').toLowerCase()];
            if (color) {
                destination = document.createElement('span');
                destination.style.color = color;
            }
        }

        if (destination !== parent) {
            parent.append(destination);
        }
        node.childNodes.forEach((child) => this.appendSafeNode(child, destination));
    }

    plainText(value) {
        const fragment = document.createDocumentFragment();
        value.replaceAll('\r', '').split('\n').forEach((line, index) => {
            if (index > 0) {
                fragment.append(document.createElement('br'));
            }
            fragment.append(document.createTextNode(line));
        });

        return fragment;
    }

    serialize(root) {
        const serializeNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                return node.textContent;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return '';
            }

            const tag = node.tagName.toLowerCase();
            if (tag === 'br') {
                return '\n';
            }
            const content = [...node.childNodes].map(serializeNode).join('');
            if (tag === 'strong' || tag === 'b') {
                return `<strong>${content}</strong>`;
            }
            if (tag === 'em' || tag === 'i') {
                return `<em>${content}</em>`;
            }
            if (tag === 'span') {
                const color = this.constructor.colors[node.style.color.replaceAll(' ', '').toLowerCase()];
                return color ? `<span style="color:${color};">${content}</span>` : content;
            }
            if (['div', 'p', 'li', 'blockquote'].includes(tag)) {
                return `${content}\n`;
            }

            return content;
        };

        return [...root.childNodes].map(serializeNode).join('').replace(/\n{3,}/g, '\n\n').replace(/^\n+|\n+$/g, '');
    }
}
