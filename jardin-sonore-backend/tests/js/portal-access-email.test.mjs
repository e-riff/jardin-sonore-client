import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../../assets/controllers/portal_access_email_controller.js', import.meta.url), 'utf8'))
    .replace(/^import .*;\n/gm, '')
    .replace('export default class extends Controller', 'globalThis.EmailController = class extends Controller');

function makeController(fetch) {
    const fields = Object.fromEntries(['organization', 'email', 'contact-type', 'person-first-name', 'person-last-name'].map((name) => [name, {
        value: '', addEventListener() {}, removeEventListener() {}, closest() { return { hidden: false }; },
    }]));
    class TomSelect {
        constructor() { this.options = []; this.value = ''; }
        getValue() { return this.value; }
        clear() { this.value = ''; }
        clearOptions() { this.options = []; }
        addOptions(items) { this.options.push(...items); }
        addOption(item) { this.options.push(item); }
        setValue(value) { this.value = value; }
        refreshOptions() {}
        destroy() {}
    }
    const context = { Controller: class {}, TomSelect, fetch, URL, window: { location: { origin: 'https://example.test' } } };
    vm.runInNewContext(source, context);
    const controller = new context.EmailController();
    controller.urlValue = '/emails';
    controller.element = { form: { querySelector(selector) { return fields[selector.match(/target="([^"]+)/)[1]]; } }, addEventListener() {}, removeEventListener() {} };
    controller.connect();
    return { controller, fields };
}

test('preloads addresses for the selected organization', async () => {
    const { controller, fields } = makeController(async (url) => ({ ok: true, json: async () => ({ items: [{ email: `${url.searchParams.get('organizationId')}@example.test`, label: 'Adresse' }] }) }));
    fields.organization.value = '1';
    await controller.organizationChanged({});
    assert.equal(controller.tomSelect.options[0].email, '1@example.test');
});

test('ignores a late response from a previously selected organization', async () => {
    const pending = new Map();
    const { controller, fields } = makeController((url) => new Promise((resolve) => pending.set(url.searchParams.get('organizationId'), resolve)));
    fields.organization.value = '1';
    const first = controller.organizationChanged({});
    fields.organization.value = '2';
    const second = controller.organizationChanged({});
    pending.get('2')({ ok: true, json: async () => ({ items: [{ email: '2@example.test', label: 'Deux' }] }) });
    await second;
    pending.get('1')({ ok: true, json: async () => ({ items: [{ email: '1@example.test', label: 'Un' }] }) });
    await first;
    assert.deepEqual(controller.tomSelect.options.map((item) => item.email), ['2@example.test']);
});

test('does not restore an old selection after the user enters another email', async () => {
    let resolveFetch;
    const { controller, fields } = makeController(() => new Promise((resolve) => { resolveFetch = resolve; }));
    fields.organization.value = '2';
    controller.tomSelect.value = 'old@example.test';
    fields.email.value = 'old@example.test';
    const loading = controller.organizationChanged({});
    assert.equal(fields.email.value, '');
    fields.email.value = 'new@example.test';
    controller.clearLinkedEmailWhenManuallyEdited();
    resolveFetch({ ok: true, json: async () => ({ items: [{ email: 'old@example.test', label: 'Ancienne' }] }) });
    await loading;
    assert.equal(fields.email.value, 'new@example.test');
    assert.equal(controller.tomSelect.getValue(), '');
});
