const { test } = require('node:test');
const assert = require('node:assert/strict');
require('../www/extension/client.js');

const TOKEN = 'a'.repeat(64);
function setup(fetcher) {
    const data = {};
    const storage = {
        get: async () => ({ ...data }),
        set: async values => Object.assign(data, values),
        remove: async keys => keys.forEach(key => delete data[key]),
    };
    let time = 1000;
    const client = createQFinderClient({ storage, fetcher, now: () => time });
    const authorize = () => Object.assign(data, { token: TOKEN, expiresAt: 100 });
    return { client, data, authorize, advance: value => { time = value; } };
}
const reply = (data, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => data });

test('HTML/error responses report their HTTP status and retain the session', async () => {
    for (const status of [200, 500, 502, 504]) {
        const { client, data, authorize } = setup(async () => ({
            ok: status === 200, status,
            json: async () => { throw new SyntaxError('PRIVATE_RESPONSE_BODY'); },
        }));
        authorize();
        const result = await client.lookup('title');
        assert.ok(result.error.includes(`HTTP ${status}`));
        assert.ok(!result.error.includes('PRIVATE_RESPONSE_BODY'));
        assert.equal(data.token, TOKEN);
        assert.equal(data.lastResult, undefined);
        assert.ok((await client.lookup('retry')).error.includes(`HTTP ${status}`));
    }
});

test('network failures and timeouts are distinguished without exposing raw diagnostics', async () => {
    for (const name of ['TypeError', 'TimeoutError', 'AbortError']) {
        const { client, data, authorize } = setup(async () => {
            const error = new Error('PRIVATE_REQUEST_DETAILS'); error.name = name; throw error;
        });
        authorize();
        const result = await client.lookup('title');
        assert.match(result.error, name === 'TypeError' ? /Could not reach/ : /60 seconds/);
        assert.ok(!result.error.includes('PRIVATE_REQUEST_DETAILS'));
        assert.equal(data.token, TOKEN);
    }
});

test('timeout while reading the response body is still identified as a timeout', async () => {
    const { client, authorize } = setup(async () => ({
        ok: true, status: 200,
        json: async () => { const error = new Error(); error.name = 'AbortError'; throw error; },
    }));
    authorize();
    assert.match((await client.lookup('title')).error, /60 seconds/);
});

test('unreadable logout response does not discard a token without revocation confirmation', async () => {
    for (const payload of [null, [], 'unexpected']) {
        const { client, data, authorize } = setup(async () => reply(payload));
        authorize();
        assert.match((await client.logout()).error, /HTTP 200/);
        assert.equal(data.token, TOKEN);
    }
});

test('login displays the specific response error', async () => {
    const { client } = setup(async () => ({
        ok: false, status: 502, json: async () => { throw new SyntaxError(); },
    }));
    assert.match((await client.login('user', 'password')).error, /HTTP 502/);
});

test('login saves expiry, lookup sends bearer header without token in URL', async () => {
    let calls = 0;
    const { client, data } = setup(async (url, options) => {
        calls++;
        if (url.endsWith('/logIn')) return reply({ token: TOKEN, expiresAt: 100 });
        assert.equal(options.headers.Authorization, `Bearer ${TOKEN}`);
        assert.ok(!url.includes(TOKEN));
        assert.ok(url.includes('q=10.1234%2Fexample'));
        return reply({ articleTitle: 'Example', isWoS: true });
    });
    assert.deepEqual(await client.login('user', 'password'), { ok: true });
    assert.equal(data.expiresAt, 100);
    assert.equal((await client.lookup(' 10.1234/example ')).ok, true);
    assert.equal(data.lastResult.articleTitle, 'Example');
    assert.equal(calls, 2);
});

test('expired or legacy sessions clear local state without an external lookup', async () => {
    const { client, data, authorize, advance } = setup(async () => { throw Error('must not fetch'); });
    authorize(); data.lastResult = { title: 'old' }; advance(100000);
    assert.equal((await client.lookup('title')).authRequired, true);
    assert.equal(data.token, undefined);
    assert.equal(data.lastResult, undefined);
    data.token = TOKEN;
    assert.equal((await client.status()).authenticated, false);
});

test('backend rejection clears session and results', async () => {
    const { client, data, authorize } = setup(async () => reply({ error: 'revoked' }, 401));
    authorize();
    assert.equal((await client.lookup('title')).authRequired, true);
    assert.equal(data.token, undefined);
});

test('logout revokes on backend; failed revocation retains session for retry', async () => {
    let failing = true;
    const { client, data, authorize } = setup(async (url, options) => {
        assert.ok(url.endsWith('/ajax/revokeToken'));
        assert.equal(options.method, 'POST');
        assert.equal(options.headers.Authorization, `Bearer ${TOKEN}`);
        return failing ? reply({ error: 'retry' }, 503) : reply({ ok: true });
    });
    authorize();
    assert.equal((await client.logout()).error, 'retry');
    assert.equal(data.token, TOKEN);
    failing = false;
    assert.equal((await client.logout()).ok, true);
    assert.equal(data.token, undefined);
});

test('duplicate requests suppressed and late result cannot restore a logged-out session', async () => {
    let finish;
    let started;
    const waiting = new Promise(resolve => { started = resolve; });
    const { client, data, authorize } = setup(async url => {
        if (url.endsWith('/revokeToken')) return reply({ ok: true });
        started();
        return new Promise(resolve => { finish = resolve; });
    });
    authorize();
    const lookup = client.lookup('title');
    await waiting;
    assert.match((await client.lookup('title')).error, /in progress/);
    await client.logout();
    finish(reply({ articleTitle: 'late' }));
    assert.equal((await lookup).authRequired, true);
    assert.equal(data.lastResult, undefined);
    assert.equal(data.token, undefined);
});

test('blank and oversized input never calls backend', async () => {
    const { client } = setup(async () => { throw Error('must not fetch'); });
    assert.match((await client.lookup('   ')).error, /Enter/);
    assert.match((await client.lookup('é'.repeat(1001))).error, /too long/);
});

test('popup submits the entered query and renders the returned article', async () => {
    const vm = require('node:vm');
    const fs = require('node:fs');
    const elements = new Map();
    function element(id) {
        if (!elements.has(id)) elements.set(id, {
            style: {}, dataset: {}, value: '', textContent: '', innerHTML: '', disabled: false,
            events: {}, addEventListener(type, listener) { this.events[type] = listener; },
        });
        return elements.get(id);
    }
    const result = { articleTitle: 'Matched article', journal: 'Journal', isWoS: true, quartile: 'Q2', issn: '1234-5678' };
    let stored;
    let command;
    let initialize;
    const chrome = {
        runtime: { sendMessage: async message => {
            command = message;
            if (message.type === 'AUTH_STATUS') return { authenticated: true };
            if (message.type === 'LOOKUP') { stored = result; return { ok: true, data: result }; }
        } },
        storage: { local: { get: async () => ({ lastResult: stored }) }, onChanged: { addListener() {} } },
    };
    const document = {
        getElementById: element,
        addEventListener: (type, listener) => { if (type === 'DOMContentLoaded') initialize = listener; },
    };
    vm.runInNewContext(fs.readFileSync(require.resolve('../www/extension/popup.js'), 'utf8'), { chrome, document });
    await initialize();
    element('searchQuery').value = '10.1234/example';
    await element('searchForm').events.submit({ preventDefault() {} });
    assert.equal(command.type, 'LOOKUP');
    assert.equal(command.query, '10.1234/example');
    assert.equal(element('articleTitle').textContent, 'Matched article');
    assert.equal(element('pillQuartile').textContent, 'Q2');
    assert.equal(element('searchBtn').disabled, false);
});

test('temporary service errors leave valid session intact and allow retry', async () => {
    let failing = true;
    const { client, data, authorize } = setup(async () => failing
        ? reply({ error: 'Lookup temporarily unavailable.' }, 503)
        : reply({ articleTitle: 'Recovered' }));
    authorize();
    assert.match((await client.lookup('title')).error, /temporarily/);
    assert.equal(data.token, TOKEN);
    failing = false;
    assert.equal((await client.lookup('title')).ok, true);
    assert.equal(data.lastResult.articleTitle, 'Recovered');
});
