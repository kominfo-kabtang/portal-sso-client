/**
 * Contoh integrasi Portal ASN untuk Express + express-session.
 *
 *   npm i express express-session @kominfo-kabtang/portal-sso-client
 *   SSO_HOST=... SSO_HOST_DOMAIN=... SSO_CLIENT_ID=... SSO_CLIENT_SECRET=... \
 *   APP_URL=https://app.example SESSION_SECRET=... node express.js
 */
const express = require('express');
const session = require('express-session');
const { createPortalSso, configFromEnv, SsoError, MESSAGES } = require('@kominfo-kabtang/portal-sso-client');

const APP_URL = process.env.APP_URL || 'http://localhost:3000';
const sso = createPortalSso(configFromEnv(process.env, {
  callbackUrl: `${APP_URL}/callback`, // = redirect URI OAuth client di portal
  logger: (message, context) => console.warn(message, context),
}));

const app = express();
app.use(session({ secret: process.env.SESSION_SECRET, resave: false, saveUninitialized: false }));

// Sesuaikan: cari user lokal berdasarkan NIP. null = ditolak.
async function findUserByNip(nip) {
  return null;
}

async function loginLocalUser(req, res, login) {
  const user = await findUserByNip(login.nip);
  if (!user) throw new SsoError(MESSAGES.UNKNOWN_USER);

  req.session.regenerate((err) => {
    if (err) return fail(req, res, new SsoError(MESSAGES.USER_FAILED));
    req.session.userId = user.id;
    req.session.accessToken = login.token;
    res.redirect('/');
  });
}

function fail(req, res, e) {
  if (!(e instanceof SsoError)) throw e;
  if (Object.keys(e.context).length) console.warn('Portal SSO gagal:', e.message, e.context);
  res.redirect(`/login?error=${encodeURIComponent(e.message)}`);
}

// Alur B: tombol "Masuk lewat Portal ASN"
app.get('/sso/login', (req, res) => {
  try {
    res.redirect(sso.begin(req.session));
  } catch (e) {
    fail(req, res, e);
  }
});

app.get('/callback', async (req, res) => {
  try {
    await loginLocalUser(req, res, await sso.handleCallback(req.session, req.query));
  } catch (e) {
    fail(req, res, e);
  }
});

// Alur A: tile aplikasi di portal
app.get('/callback-session', async (req, res) => {
  try {
    await loginLocalUser(req, res, await sso.handleCallbackSession(req.query));
  } catch (e) {
    fail(req, res, e);
  }
});

app.post('/sso/logout', async (req, res) => {
  const portalLogoutUrl = await sso.logout(req.session.accessToken);
  req.session.destroy(() => res.redirect(portalLogoutUrl || '/login'));
});

app.listen(3000);
