'use strict';

/**
 * Client SSO Portal ASN Kabupaten Tangerang untuk backend Node.js (18+).
 * Padanan src/Core di paket PHP: protokol dan pesan error harus tetap sama.
 *
 * Jangan dipakai langsung di browser: client_secret harus tetap di server.
 */

const crypto = require('crypto');

const STATE_KEY = 'portal_sso_state';

const MESSAGES = Object.freeze({
  NOT_CONFIGURED: 'Konfigurasi SSO Portal ASN belum lengkap.',
  INVALID_STATE: 'State SSO tidak valid. Silakan ulangi login.',
  MISSING_CODE: 'Kode otorisasi SSO tidak ditemukan.',
  EXCHANGE_FAILED: 'Gagal menukar kode SSO dengan token Portal ASN.',
  USER_FAILED: 'Gagal mengambil data pengguna dari Portal ASN.',
  MISSING_SESSION_TOKEN: 'Token session Portal ASN tidak ditemukan.',
  INVALID_SESSION_TOKEN: 'Token session Portal ASN tidak valid.',
  MISSING_NIP: 'Data pengguna Portal ASN tidak memuat NIP.',
  UNKNOWN_USER: 'Akun Anda belum terdaftar di aplikasi ini.',
});

/**
 * message aman ditampilkan ke user; context untuk log (tanpa secret/code/token).
 */
class SsoError extends Error {
  constructor(message, context = {}, cause = undefined) {
    super(message);
    this.name = 'SsoError';
    this.context = context;
    if (cause !== undefined) this.cause = cause;
  }
}

const filled = (value) => value !== undefined && value !== null && String(value).trim() !== '';
const trimSlash = (value) => (filled(value) ? String(value).replace(/\/+$/, '') : '');
const str = (value) => (typeof value === 'string' ? value : '');

function safeEqual(a, b) {
  const x = Buffer.from(a);
  const y = Buffer.from(b);
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}

/**
 * Baca SSO_* dari environment (mis. process.env).
 */
function configFromEnv(env = process.env, overrides = {}) {
  const pick = (name) => (filled(env[name]) ? env[name] : undefined);
  const config = {
    mode: pick('SSO_MODE'),
    host: pick('SSO_HOST'),
    hostDomain: pick('SSO_HOST_DOMAIN'),
    clientId: pick('SSO_CLIENT_ID'),
    clientSecret: pick('SSO_CLIENT_SECRET'),
    callbackUrl: pick('SSO_CLIENT_CALLBACK'),
    scopes: pick('SSO_SCOPES'),
    timeoutMs: filled(env.SSO_TIMEOUT) ? Number(env.SSO_TIMEOUT) * 1000 : undefined,
    logoutTimeoutMs: filled(env.SSO_LOGOUT_TIMEOUT) ? Number(env.SSO_LOGOUT_TIMEOUT) * 1000 : undefined,
  };
  Object.keys(config).forEach((key) => config[key] === undefined && delete config[key]);
  return Object.assign(config, overrides);
}

/**
 * @param {object} options
 * @param {'legacy'|'oauth'} [options.mode='legacy'] legacy: /request + /api/token-user.
 *   oauth: OAuth2 standar (/oauth/authorize + /oauth/token) dengan client milik aplikasi.
 * @param {string} options.host         Portal untuk panggilan server (boleh IP internal).
 * @param {string} [options.hostDomain] Portal untuk redirect browser. Default: host.
 * @param {string} [options.clientId]
 * @param {string} [options.clientSecret]
 * @param {string} options.callbackUrl  Harus sama dengan redirect URI OAuth client di portal.
 * @param {string} [options.scopes='view-user']
 * @param {number} [options.timeoutMs=15000]
 * @param {number} [options.logoutTimeoutMs=5000]
 * @param {Function} [options.fetch]    Default: global fetch.
 * @param {Function} [options.logger]   (message, context) => void
 */
function createPortalSso(options = {}) {
  const host = trimSlash(options.host);
  const hostDomain = trimSlash(options.hostDomain) || host;
  const scopes = options.scopes === undefined ? 'view-user' : options.scopes;
  const timeoutMs = options.timeoutMs || 15000;
  const logoutTimeoutMs = options.logoutTimeoutMs || 5000;
  const fetchFn = options.fetch || globalThis.fetch;
  // Nilai selain "oauth" dianggap "legacy" agar aplikasi lama tidak berubah perilaku.
  const oauth = String(options.mode || '').trim().toLowerCase() === 'oauth';
  const logger = options.logger || (() => {});

  if (typeof fetchFn !== 'function') {
    throw new Error('fetch tidak tersedia. Pakai Node.js 18+ atau isi opsi "fetch".');
  }

  // Mode oauth memakai client milik aplikasi, jadi client_id dan client_secret wajib.
  const isConfigured = () => host !== '' && hostDomain !== ''
    && (!oauth || (filled(options.clientId) && filled(options.clientSecret)));

  function authorizeUrl(state) {
    const params = new URLSearchParams();
    const values = {
      client_id: options.clientId,
      redirect_uri: options.callbackUrl,
      response_type: 'code',
      scope: scopes,
      state,
    };
    if (!oauth) values.prompt = '1';
    Object.keys(values).forEach((key) => filled(values[key]) && params.append(key, String(values[key])));
    return `${hostDomain}${oauth ? '/oauth/authorize' : '/request'}?${params.toString()}`;
  }

  async function send(method, path, { token, form, timeout = timeoutMs } = {}) {
    const headers = { Accept: 'application/json' };
    if (token !== undefined) headers.Authorization = `Bearer ${token}`;

    let body;
    if (form) {
      const params = new URLSearchParams();
      Object.keys(form).forEach((key) => filled(form[key]) && params.append(key, String(form[key])));
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      body = params.toString();
    }

    const res = await fetchFn(`${host}${path}`, {
      method,
      headers,
      body,
      redirect: 'manual',
      signal: AbortSignal.timeout(timeout),
    });
    const text = await res.text();
    let json = null;
    try {
      const parsed = JSON.parse(text);
      json = parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      json = null;
    }
    return { status: res.status, ok: res.status >= 200 && res.status < 300, json, text };
  }

  // Parameter "url" sengaja tidak dikirim (kasus SSRF di portal).
  // Mode oauth: state sudah dicocokkan handleCallback; /oauth/token memeriksa secret dan redirect_uri.
  const exchangeCode = (code, state) => (oauth ? send('POST', '/oauth/token', {
    form: {
      grant_type: 'authorization_code',
      client_id: options.clientId,
      client_secret: options.clientSecret,
      redirect_uri: options.callbackUrl,
      code,
    },
  }) : send('POST', '/api/token-user', {
    form: {
      state,
      grant_type: 'authorization_code',
      redirect_uri: options.callbackUrl,
      code,
      client_id: options.clientId,
      client_secret: options.clientSecret,
    },
  }));
  const get = (token, path) => send('GET', path, { token });
  const user = (token) => get(token, '/api/user');
  const verifyToken = (token) => get(token, '/api/verify-token');
  const checkUser = (token) => get(token, '/api/cek-user');
  const logoutUrl = () => `${hostDomain}/logout-api`;

  /** Cabut semua token user di portal. Tidak pernah reject. */
  async function revoke(token) {
    try {
      return (await send('GET', '/api/logmeout', { token, timeout: logoutTimeoutMs })).ok;
    } catch (e) {
      logger('Portal SSO logmeout gagal, lanjut logout lokal', { error: e.message });
      return false;
    }
  }

  async function call(message, request) {
    let res;
    try {
      res = await request();
    } catch (e) {
      throw new SsoError(message, { error: e.message }, e);
    }
    if (!res.ok) throw new SsoError(message, { http_status: res.status });
    return res;
  }

  function toLogin(res, token, flow) {
    const data = res.json;
    if (!data || Array.isArray(data) || String(data.nip == null ? '' : data.nip).trim() === '') {
      throw new SsoError(MESSAGES.MISSING_NIP);
    }
    return { user: data, token, flow, nip: String(data.nip).trim() };
  }

  /**
   * Alur B langkah 1. session: objek session per user (mis. req.session).
   * @returns {string} URL portal untuk redirect.
   */
  function begin(session) {
    if (!isConfigured()) throw new SsoError(MESSAGES.NOT_CONFIGURED);
    const state = crypto.randomBytes(20).toString('hex');
    session[STATE_KEY] = state;
    return authorizeUrl(state);
  }

  /**
   * Alur B langkah 2: /callback?code=&state=.
   * @returns {Promise<{user: object, token: string, flow: string, nip: string}>}
   */
  async function handleCallback(session, query = {}) {
    // State sekali pakai: selalu dihapus, valid atau tidak.
    const state = session[STATE_KEY];
    delete session[STATE_KEY];
    if (typeof state !== 'string' || state === '' || !safeEqual(state, str(query.state))) {
      throw new SsoError(MESSAGES.INVALID_STATE);
    }

    const code = str(query.code);
    if (code === '') throw new SsoError(MESSAGES.MISSING_CODE);

    const res = await call(MESSAGES.EXCHANGE_FAILED, () => exchangeCode(code, state));
    const token = res.json && res.json.access_token;
    if (typeof token !== 'string' || token === '') {
      throw new SsoError(MESSAGES.EXCHANGE_FAILED, { http_status: res.status });
    }

    return toLogin(await call(MESSAGES.USER_FAILED, () => user(token)), token, 'oauth');
  }

  /**
   * Alur A: tile portal → /callback-session?token=.
   */
  async function handleCallbackSession(query = {}) {
    const token = str(query.token);
    if (token === '') throw new SsoError(MESSAGES.MISSING_SESSION_TOKEN);

    await call(MESSAGES.INVALID_SESSION_TOKEN, () => verifyToken(token));

    return toLogin(await call(MESSAGES.USER_FAILED, () => checkUser(token)), token, 'portal-tile');
  }

  /**
   * Cabut token (tidak pernah gagal). Hasil: URL logout portal untuk
   * redirect browser, atau null. Logout lokal tetap urusan aplikasi.
   */
  async function logout(token) {
    if (typeof token !== 'string' || token === '') return null;
    if (host !== '') await revoke(token);
    return hostDomain !== '' ? logoutUrl() : null;
  }

  return {
    isConfigured,
    authorizeUrl,
    exchangeCode,
    user,
    verifyToken,
    checkUser,
    get,
    revoke,
    logoutUrl,
    begin,
    handleCallback,
    handleCallbackSession,
    logout,
  };
}

module.exports = { createPortalSso, configFromEnv, SsoError, MESSAGES, STATE_KEY };
