'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { createPortalSso, configFromEnv, SsoError, MESSAGES, STATE_KEY } = require('../src/index.js');

function fakeFetch(routes) {
  const sent = [];
  const fn = async (url, init) => {
    const path = new URL(url).pathname;
    sent.push({ url, path, ...init, form: init.body ? Object.fromEntries(new URLSearchParams(init.body)) : undefined });
    const route = routes[path];
    if (route instanceof Error) throw route;
    const [status, body] = route || [404, ''];
    return { status, text: async () => (typeof body === 'string' ? body : JSON.stringify(body)) };
  };
  fn.sent = sent;
  return fn;
}

function sso(fetch, extra = {}) {
  return createPortalSso({
    host: 'http://10.0.0.1/',
    hostDomain: 'https://portal.test',
    clientId: '7',
    clientSecret: 'rahasia',
    callbackUrl: 'https://app.test/callback',
    fetch,
    ...extra,
  });
}

async function rejectsWith(promise, message, context) {
  await assert.rejects(promise, (e) => {
    assert.ok(e instanceof SsoError);
    assert.equal(e.message, message);
    if (context) assert.deepEqual(e.context, context);
    return true;
  });
}

test('begin stores state and builds authorize url', () => {
  const session = {};
  const url = new URL(sso(fakeFetch({})).begin(session));

  assert.equal(session[STATE_KEY].length, 40);
  assert.equal(url.origin + url.pathname, 'https://portal.test/request');
  assert.equal(url.searchParams.get('state'), session[STATE_KEY]);
  assert.equal(url.searchParams.get('client_id'), '7');
  assert.equal(url.searchParams.get('redirect_uri'), 'https://app.test/callback');
  assert.equal(url.searchParams.get('response_type'), 'code');
  assert.equal(url.searchParams.get('scope'), 'view-user');
});

test('begin fails when not configured', () => {
  assert.throws(() => sso(fakeFetch({}), { host: '', hostDomain: '' }).begin({}), { message: MESSAGES.NOT_CONFIGURED });
});

test('callback rejects invalid and reused state without calling portal', async () => {
  const fetch = fakeFetch({});
  const client = sso(fetch);
  const session = { [STATE_KEY]: 'benar' };

  await rejectsWith(client.handleCallback(session, { code: 'abc', state: 'salah' }), MESSAGES.INVALID_STATE);
  await rejectsWith(client.handleCallback(session, { code: 'abc', state: 'benar' }), MESSAGES.INVALID_STATE);
  await rejectsWith(client.handleCallback({ [STATE_KEY]: 'st' }, { code: ['x'], state: 'st' }), MESSAGES.MISSING_CODE);
  assert.equal(fetch.sent.length, 0);
});

test('callback exchanges code and returns portal user', async () => {
  const fetch = fakeFetch({
    '/api/token-user': [200, { access_token: 'tok-123' }],
    '/api/user': [200, { nip: '199001012020121001', name: 'Portal' }],
  });

  const login = await sso(fetch).handleCallback({ [STATE_KEY]: 'st' }, { code: 'abc', state: 'st' });

  assert.equal(login.token, 'tok-123');
  assert.equal(login.nip, '199001012020121001');
  assert.equal(login.flow, 'oauth');

  const [exchange, user] = fetch.sent;
  assert.equal(exchange.method, 'POST');
  assert.equal(exchange.url, 'http://10.0.0.1/api/token-user');
  assert.equal(exchange.form.code, 'abc');
  assert.equal(exchange.form.client_secret, 'rahasia');
  assert.equal(exchange.form.redirect_uri, 'https://app.test/callback');
  assert.equal(exchange.form.url, undefined);
  assert.equal(user.headers.Authorization, 'Bearer tok-123');
});

test('callback turns portal failures into safe errors', async () => {
  const cases = [
    [new Error('ECONNREFUSED'), { error: 'ECONNREFUSED' }],
    [[500, {}], { http_status: 500 }],
    [[200, { foo: 'bar' }], { http_status: 200 }],
  ];
  for (const [route, context] of cases) {
    const client = sso(fakeFetch({ '/api/token-user': route }));
    await rejectsWith(client.handleCallback({ [STATE_KEY]: 'st' }, { code: 'abc', state: 'st' }), MESSAGES.EXCHANGE_FAILED, context);
  }
});

test('callback requires nip', async () => {
  const fetch = fakeFetch({
    '/api/token-user': [200, { access_token: 'tok' }],
    '/api/user': [200, { name: 'Tanpa NIP' }],
  });
  await rejectsWith(sso(fetch).handleCallback({ [STATE_KEY]: 'st' }, { code: 'abc', state: 'st' }), MESSAGES.MISSING_NIP);
});

test('callback-session verifies token then fetches user', async () => {
  const fetch = fakeFetch({
    '/api/verify-token': [200, { valid: true }],
    '/api/cek-user': [200, { nip: 222 }],
  });

  const login = await sso(fetch).handleCallbackSession({ token: 'tile-tok' });

  assert.equal(login.nip, '222');
  assert.equal(login.flow, 'portal-tile');
  assert.deepEqual(fetch.sent.map((r) => r.path), ['/api/verify-token', '/api/cek-user']);
});

test('callback-session rejects invalid token', async () => {
  const fetch = fakeFetch({ '/api/verify-token': [401, {}] });
  await rejectsWith(sso(fetch).handleCallbackSession({ token: 'palsu' }), MESSAGES.INVALID_SESSION_TOKEN);
  assert.equal(fetch.sent.length, 1);
});

test('logout revokes token and returns portal url even when portal is down', async () => {
  const logs = [];
  const fetch = fakeFetch({ '/api/logmeout': new Error('ECONNREFUSED') });

  const url = await sso(fetch, { logger: (m) => logs.push(m) }).logout('tok');

  assert.equal(url, 'https://portal.test/logout-api');
  assert.equal(fetch.sent[0].headers.Authorization, 'Bearer tok');
  assert.equal(logs.length, 1);
  assert.equal(await sso(fetch).logout(undefined), null);
  assert.equal(fetch.sent.length, 1);
});

test('configFromEnv reads SSO_* variables', () => {
  const config = configFromEnv({ SSO_HOST: 'http://env-host', SSO_TIMEOUT: '3' }, { callbackUrl: 'https://app.test/callback' });
  assert.deepEqual(config, { host: 'http://env-host', timeoutMs: 3000, callbackUrl: 'https://app.test/callback' });
  assert.equal(sso(undefined, { ...config, hostDomain: undefined }).logoutUrl(), 'http://env-host/logout-api');
});
