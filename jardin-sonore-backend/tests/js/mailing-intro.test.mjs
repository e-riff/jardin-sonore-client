import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../../assets/controllers/mailing_intro_controller.js', import.meta.url), 'utf8'))
    .replace(/^import .*;\n/gm, '')
    .replace('export default class extends Controller', 'globalThis.MailingIntroController = class extends Controller');

function fixture(prefix) {
    const context = { Controller: class {} };
    vm.runInNewContext(source, context);
    const controller = new context.MailingIntroController();
    const attributes = { name: `${prefix}[1][text]`, id: `${prefix.replaceAll('][', '_').replaceAll('[', '_').replaceAll(']', '')}_1_text`, for: null };
    const element = { getAttribute: (key) => attributes[key], setAttribute: (key, value) => { attributes[key] = value; } };
    const row = { matches: () => true, querySelectorAll: () => [element] };
    controller.collectionTarget = { children: [row] };
    return { controller, attributes };
}

test('renumbers a section block after removing its predecessor so adding another cannot overwrite it', () => {
    const { controller, attributes } = fixture('edit_mailing_campaign[sections][editorial][blocks]');
    controller.reindex();
    assert.equal(attributes.name, 'edit_mailing_campaign[sections][editorial][blocks][0][text]');
    assert.equal(attributes.id, 'edit_mailing_campaign_sections_editorial_blocks_0_text');
});

test('preserves legacy introduction reindexing', () => {
    const { controller, attributes } = fixture('edit_mailing_campaign[introBlocks]');
    controller.reindex();
    assert.equal(attributes.name, 'edit_mailing_campaign[introBlocks][0][text]');
    assert.equal(attributes.id, 'edit_mailing_campaign_introBlocks_0_text');
});
