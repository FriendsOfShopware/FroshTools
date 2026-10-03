import { test } from 'node:test';
import assert from 'node:assert/strict';
import worker from './index.mjs';

const env = { CHECK_TOKEN: 'FroshTools' };
const payload = { appUrl: 'https://shop.example.com/subdir/', challenge: 'a'.repeat(64), expectedProof: 'b'.repeat(64) };

function request(data = payload, token = env.CHECK_TOKEN) {
    return new Request('https://worker.example.com', {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: JSON.stringify(data),
    });
}

test('calls APP_URL from the Worker and checks the exact proof', async (t) => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        calls++;
        assert.equal(
            String(url),
            `https://shop.example.com/subdir/api/_action/frosh-tools/apps/reachability-probe?challenge=${payload.challenge}`,
        );
        assert.equal(options.redirect, 'manual');
        assert.equal(options.headers.Authorization, undefined);
        assert.ok(options.signal instanceof AbortSignal);
        assert.equal(options.cf.cacheTtl, 0);
        return Response.json({ proof: payload.expectedProof });
    });
    const response = await worker.fetch(request(), env);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
    assert.deepEqual(await response.json(), { status: 'pass', proof: payload.expectedProof, info: null });
    assert.equal(calls, 1);
});

for (const [name, result] of [
    ['wrong shop', { proof: 'c'.repeat(64) }],
    ['missing proof', {}],
    ['null body', null],
]) {
    test(`rejects ${name}`, async (t) => {
        t.mock.method(globalThis, 'fetch', async () => Response.json(result));
        assert.equal((await (await worker.fetch(request(), env)).json()).status, 'hard_fail');
    });
}
for (const [code, status] of [
    [400, 'hard_fail'],
    [404, 'hard_fail'],
    [302, 'hard_fail'],
    [429, 'soft_fail'],
    [500, 'soft_fail'],
]) {
    test(`reports upstream HTTP ${code} as ${status}`, async (t) => {
        t.mock.method(globalThis, 'fetch', async () => new Response('[]', { status: code }));
        const result = await (await worker.fetch(request(), env)).json();
        assert.equal(result.status, status);
        assert.match(result.info, new RegExp(String(code)));
    });
}
test('reports connection failures and timeouts', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => {
        throw new Error('timeout');
    });
    assert.equal((await (await worker.fetch(request(), env)).json()).status, 'soft_fail');
});
test('rejects non-JSON probe responses', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => new Response('<html>not this shop</html>'));
    assert.equal((await (await worker.fetch(request(), env)).json()).status, 'hard_fail');
});
for (const appUrl of [
    'http://shop.example.com',
    'https://127.0.0.1',
    'https://[::1]',
    'https://localhost',
    'https://host.internal',
    'https://shop.example.com:8443',
    'https://user:pass@shop.example.com',
    'https://shop.example.com/?target=elsewhere',
]) {
    test(`rejects unsupported URL ${appUrl}`, async (t) => {
        const fetchMock = t.mock.method(globalThis, 'fetch', async () => assert.fail('Must not fetch invalid targets'));
        assert.equal((await worker.fetch(request({ ...payload, appUrl }), env)).status, 400);
        assert.equal(fetchMock.mock.callCount(), 0);
    });
}
test('rejects missing access token before making a request', async (t) => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => assert.fail('Must not fetch without auth'));
    assert.equal((await worker.fetch(request(payload, 'wrong'), env)).status, 401);
    assert.equal((await worker.fetch(request(), {})).status, 503);
    assert.equal(fetchMock.mock.callCount(), 0);
});
test('rejects malformed input and unsupported methods', async () => {
    assert.equal((await worker.fetch(request({ ...payload, challenge: 'bad' }), env)).status, 400);
    assert.equal((await worker.fetch(request(null), env)).status, 400);
    assert.equal((await worker.fetch(request({ ...payload, expectedProof: null }), env)).status, 400);
    assert.equal((await worker.fetch(new Request('https://worker.example.com'), env)).status, 405);
    const invalid = new Request('https://worker.example.com', {
        method: 'POST',
        headers: { Authorization: `Bearer ${env.CHECK_TOKEN}` },
        body: '{',
    });
    assert.equal((await worker.fetch(invalid, env)).status, 400);
    const oversized = new Request('https://worker.example.com', {
        method: 'POST',
        headers: { Authorization: `Bearer ${env.CHECK_TOKEN}` },
        body: ' '.repeat(4097),
    });
    assert.equal((await worker.fetch(oversized, env)).status, 413);
});
