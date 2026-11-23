import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

// Exercise the actual component effect with controlled browser surfaces. These
// tests validate privacy transitions; they do not substitute for browser UAT.
const source = readFileSync(new URL('../resources/js/components/session-security.tsx', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX } }).outputText;

function sessionHarness() {
    let clock = 1_800_000_000_000;
    const security = { userId: 7, serverNow: clock, expiresAt: clock + 900_000, idleMinutes: 15, mustChangePassword: false };
    const windowEvents = new Map();
    const documentEvents = new Map();
    const states = [];
    const requests = [];
    const navigations = [];
    const messages = [];
    let cleanup;
    let tick;
    let channel;
    let respond = async () => ({ ok: true, status: 200, json: async () => ({ userId: 7, serverNow: clock, expiresAt: security.expiresAt }) });
    const document = { body: { style: { visibility: '' } }, cookie: '', visibilityState: 'visible', addEventListener: (name, fn) => documentEvents.set(name, fn), removeEventListener: (name) => documentEvents.delete(name) };
    const window = {
        location: { assign: (url) => navigations.push(url) },
        addEventListener: (name, fn) => windowEvents.set(name, fn), removeEventListener: (name) => windowEvents.delete(name),
        setInterval: (fn) => { tick = fn; return 1; },
    };
    class ControlledDate extends Date { static now() { return clock; } }
    class Channel {
        constructor() { channel = this; }
        postMessage(message) { messages.push(message); }
        close() {}
    }
    const exports = {};
    runInNewContext(compiled, {
        exports, window, document, Date: ControlledDate, BroadcastChannel: Channel, clearInterval() {},
        fetch: async (url, options) => { requests.push({ url, options }); return respond(url, options); },
        require(name) {
            if (name === '@inertiajs/react') return { usePage: () => ({ props: { sessionSecurity: security } }) };
            if (name === 'react') return {
                useRef: (current) => ({ current }),
                useState: (initial) => { const index = states.length; states.push(initial); return [initial, (value) => { states[index] = value; }]; },
                useEffect: (effect) => { cleanup = effect(); },
            };
            if (name === 'react/jsx-runtime') return { jsx: () => null, jsxs: () => null, Fragment: 'fragment' };
            if (name === '@/components/ui/alert') return { Alert: 'alert', AlertDescription: 'description', AlertTitle: 'title' };
            if (name === '@/components/ui/button') return { Button: 'button' };
            throw new Error(`Unexpected dependency: ${name}`);
        },
    });
    exports.SessionSecurity();
    return {
        document, windowEvents, documentEvents, requests, navigations, messages, states,
        advance: (milliseconds) => { clock += milliseconds; }, tick: () => tick(), cleanup: () => cleanup(),
        respond: (fn) => { respond = fn; },
        broadcast: (data) => channel.onmessage({ data }),
    };
}

const flush = async () => { await new Promise(setImmediate); await new Promise(setImmediate); };

test('idle expiry hides old content and navigates without logging out a newer account', () => {
    const browser = sessionHarness();
    browser.advance(900_000);
    browser.tick();
    assert.equal(browser.document.body.style.visibility, 'hidden');
    assert.deepEqual(browser.navigations, ['/login']);
    assert.equal(browser.requests.length, 0);
    assert.equal(browser.messages.at(-1).type, 'expired');
    browser.cleanup();
});

test('a page restored from browser cache stays hidden until its account is verified', async () => {
    const browser = sessionHarness();
    browser.windowEvents.get('pagehide')();
    assert.equal(browser.document.body.style.visibility, 'hidden');
    browser.respond(async () => ({ ok: true, status: 200, json: async () => ({ userId: 99, expiresAt: Date.now() + 900_000, serverNow: Date.now() }) }));
    browser.windowEvents.get('pageshow')({ persisted: true });
    await flush();
    assert.equal(browser.requests[0].url, '/session/status');
    assert.equal(browser.document.body.style.visibility, 'hidden');
    assert.deepEqual(browser.navigations, ['/']);
    assert.equal(browser.requests.some((request) => request.url === '/logout'), false);
    browser.cleanup();
});

test('a restored page for the same active account becomes visible without extending the session', async () => {
    const browser = sessionHarness();
    browser.windowEvents.get('pagehide')();
    browser.windowEvents.get('pageshow')({ persisted: true });
    await flush();
    assert.equal(browser.document.body.style.visibility, '');
    assert.deepEqual(browser.requests.map((request) => request.url), ['/session/status']);
    assert.deepEqual(browser.navigations, []);
    browser.cleanup();
});

test('failed session verification keeps content private and exposes a retry state', async () => {
    const browser = sessionHarness();
    browser.respond(async () => { throw new Error('Simulated offline connection'); });
    browser.documentEvents.get('visibilitychange')();
    await flush();
    assert.equal(browser.document.body.style.visibility, 'hidden');
    assert.equal(browser.states[1], 'offline');
    assert.deepEqual(browser.navigations, []);
    browser.cleanup();
});

test('account changes across tabs hide the old account without sending a logout request', () => {
    const browser = sessionHarness();
    browser.broadcast({ type: 'account', userId: 99 });
    assert.equal(browser.document.body.style.visibility, 'hidden');
    assert.deepEqual(browser.navigations, ['/']);
    assert.equal(browser.requests.length, 0);
    browser.cleanup();
});
