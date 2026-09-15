const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');

let view;
const Varchar = {
    prototype: {
        setup() {},
        fetch() { return {name: this.input.trim() || null}; }
    },
    extend(definition) { return definition; }
};
vm.runInNewContext(fs.readFileSync(
    'data/espocrm-app/client/custom/src/views/lead/fields/name.js', 'utf8'
), {define(name, dependencies, factory) { view = factory(Varchar); }});

function edit(attributes, input) {
    const state = {...attributes};
    const field = Object.assign({}, view, {
        name: 'name', input, params: {},
        model: {
            get: key => state[key],
            getFieldParam: (name, key) => key === 'maxLength' ? 100 : '$noBadCharacters'
        },
        translate: key => key,
        showValidationMessage() { this.validationShown = true; }
    });
    field.setup();
    Object.assign(state, field.fetch());
    return {state, field};
}

test('one input preserves Vietnamese full names and surname order', () => {
    const {state} = edit({}, '  Nguyễn Thị Ánh  ');
    assert.equal(state.name, 'Nguyễn Thị Ánh');
    assert.equal(state.lastName, state.name);
    assert.equal(state.firstName, null);
    assert.equal(state.middleName, null);
});

test('editing another field preserves legacy split names and title', () => {
    const original = {name: 'Anne Marie Dupont', firstName: 'Anne', middleName: 'Marie',
        lastName: 'Dupont', salutationName: 'Ms.'};
    assert.deepEqual(edit(original, original.name).state, original);
});

test('renaming a legacy lead removes stale components', () => {
    const {state} = edit({name: 'John Smith', firstName: 'John', lastName: 'Smith',
        middleName: 'A', salutationName: 'Mr.'}, 'Trần Minh');
    assert.equal(state.lastName, 'Trần Minh');
    for (const key of ['firstName', 'middleName', 'salutationName']) {
        assert.equal(state[key], null);
    }
});

test('clearing the input clears the stored name components', () => {
    const {state} = edit({name: 'John Smith', firstName: 'John', lastName: 'Smith'}, '  ');
    assert.equal(state.name, null);
    assert.equal(state.firstName, null);
    assert.equal(state.lastName, null);
});

test('enforces the storage limit without truncating Unicode or legacy names', () => {
    assert.equal(edit({}, 'Á'.repeat(100)).field.validateFullNameLength(), false);
    const tooLong = edit({}, 'Á'.repeat(101));
    assert.equal(tooLong.field.validateFullNameLength(), true);
    assert.equal(tooLong.state.name.length, 101);
    assert.equal(tooLong.field.validationShown, true);
    const original = {name: 'A'.repeat(80) + ' ' + 'B'.repeat(80),
        firstName: 'A'.repeat(80), lastName: 'B'.repeat(80)};
    const unchanged = edit(original, original.name);
    assert.equal(unchanged.field.validateFullNameLength(), false);
    assert.deepEqual(unchanged.state, original);
});

test('only Lead metadata opts into the custom name view', () => {
    const lead = JSON.parse(fs.readFileSync(
        'data/espocrm-app/custom/Espo/Custom/Resources/metadata/entityDefs/Lead.json', 'utf8'));
    assert.equal(lead.fields.name.view, 'custom:views/lead/fields/name');
    assert.equal(lead.fields.name.type, undefined);
});
