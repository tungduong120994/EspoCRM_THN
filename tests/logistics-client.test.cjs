const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('data/espocrm-app/client/custom/src/helpers/logistics.js', 'utf8');
function fixture(postRequest) {
    const storage = new Map(); let serial = 0; let helper;
    vm.runInNewContext(source, {
        define: (name, deps, factory) => { helper = factory(); },
        sessionStorage: {getItem: k => storage.get(k), setItem: (k,v) => storage.set(k,v), removeItem: k => storage.delete(k)},
        crypto: {randomUUID: () => 'request-' + (++serial)},
        Espo: {Ajax: {postRequest}},
    });
    return {helper, storage, view: {getUser: () => ({id: 'user-a'})}};
}
test('retry after lost response reuses request key and clears it only after success', async () => {
    const calls = [];
    const f = fixture(async (url,data) => { calls.push(data); if (calls.length === 1) throw Error('connection lost'); return {creditVnd:10}; });
    const data = {accountId:'a',amountVnd:10};
    await assert.rejects(f.helper.post(f.view,'receipt',data));
    assert.equal(f.storage.size,1);
    await f.helper.post(f.view,'receipt',data);
    assert.equal(calls[0].requestKey,calls[1].requestKey);
    assert.equal(f.storage.size,0);
    await f.helper.post(f.view,'receipt',data);
    assert.notEqual(calls[1].requestKey,calls[2].requestKey);
});
test('changed receipt payload receives a new key after failure', async () => {
    const calls=[]; const f=fixture(async (url,data)=>{calls.push(data); throw Error('failed');});
    await assert.rejects(f.helper.post(f.view,'receipt',{accountId:'a',amountVnd:10}));
    await assert.rejects(f.helper.post(f.view,'receipt',{accountId:'a',amountVnd:20}));
    assert.notEqual(calls[0].requestKey,calls[1].requestKey);
});
test('action binding forwards tracking account data and prevents default navigation', () => {
    const f=fixture(); let handler; let account; let prevented=false;
    f.helper.bind({addActionHandler:(name,fn)=>{assert.equal(name,'tracking');handler=fn;},actionTracking:data=>{account=data.id;}},['tracking']);
    handler({preventDefault:()=>{prevented=true;}},{dataset:{id:'customer-a'}});
    assert.equal(account,'customer-a'); assert.equal(prevented,true);
});
test('delivery template chooses the stored snapshot before evaluating legacy live data', () => {
    const text=fs.readFileSync('templates/delivery-note.tpl','utf8');
    assert.ok(text.startsWith('{{#if invoiceSnapshotHtml}}\n{{{invoiceSnapshotHtml}}}\n{{else}}'));
    const stack=[];
    for(const match of text.matchAll(/{{\s*([#\/])([a-zA-Z]+)[^}]*}}/g)) {
        if(match[1]==='#') stack.push(match[2]);
        else assert.equal(stack.pop(),match[2]);
    }
    assert.deepEqual(stack,[]);
    assert.ok(text.trimEnd().endsWith('{{/if}}'));
});
