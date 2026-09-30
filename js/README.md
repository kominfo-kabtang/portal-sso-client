# @kominfo-kabtang/portal-sso-client

Client login SSO **Portal ASN Kabupaten Tangerang** untuk backend Node.js 18+ (Express, Fastify, Koa, NestJS, route handler Next.js/Nuxt). Paket ini tidak punya dependensi.

> Paket ini khusus untuk **server**. Pertukaran code memakai `client_secret`, jadi jangan dipakai di bundle browser (Vue/React/Vite). Untuk SPA, lihat [bagian SPA di README utama](../README.md#aplikasi-spa-vue-react).

## Instalasi

```bash
npm i @kominfo-kabtang/portal-sso-client
```

Environment (sama dengan paket PHP):

```dotenv
SSO_HOST=http://ip-internal-portal        # panggilan server-ke-server
SSO_HOST_DOMAIN=https://portal-asn.tangerangkab.go.id   # redirect browser
SSO_CLIENT_ID=
SSO_CLIENT_SECRET=
SSO_SCOPES=view-user
```

## Pemakaian

```js
const { createPortalSso, configFromEnv, SsoError, MESSAGES } = require('@kominfo-kabtang/portal-sso-client');
// atau: import { createPortalSso } from '@kominfo-kabtang/portal-sso-client';

const sso = createPortalSso(configFromEnv(process.env, {
  callbackUrl: 'https://app.example/callback', // = redirect URI OAuth client di portal
  logger: (message, context) => console.warn(message, context),
}));
```

| Rute aplikasi | Panggil |
|---|---|
| `GET /sso/login` | `res.redirect(sso.begin(req.session))`. State disimpan di objek session |
| `GET /callback` | `await sso.handleCallback(req.session, req.query)` → `{ user, token, flow, nip }` |
| `GET /callback-session` | `await sso.handleCallbackSession(req.query)` → `{ user, token, flow, nip }` |
| `POST /sso/logout` | `const url = await sso.logout(token)`. Hapus session lokal, lalu `redirect(url \|\| '/login')` |

Setelah `handleCallback`/`handleCallbackSession` berhasil, aplikasi mencocokkan `login.nip` ke user lokal. Bila user tidak ditemukan, tolak dengan `MESSAGES.UNKNOWN_USER`. Bila ditemukan, simpan login dan `login.token` di session. `logout()` memakai token itu.

Semua kegagalan, termasuk portal yang tidak terjangkau, di-reject sebagai `SsoError`. `message` aman ditampilkan ke user, sedangkan `context` untuk log dan tidak memuat secret, code, atau token. `logout()` tidak pernah reject, jadi logout lokal tetap jalan walau portal mati.

Contoh lengkap Express + express-session ada di [`examples/express.js`](examples/express.js).

### Opsi `createPortalSso`

| Opsi | Default | Keterangan |
|---|---|---|
| `mode` | `legacy` | `oauth` untuk OAuth2 standar dengan client milik aplikasi (lihat README utama) |
| `host` | — | Portal untuk panggilan server (boleh IP internal) |
| `hostDomain` | `host` | Portal untuk redirect browser |
| `clientId`, `clientSecret` | — | OAuth client dari portal |
| `callbackUrl` | — | Wajib. `{APP_URL}/callback` |
| `scopes` | `view-user` | |
| `timeoutMs` / `logoutTimeoutMs` | 15000 / 5000 | |
| `fetch` | `globalThis.fetch` | Untuk proxy atau test |
| `logger` | — | `(message, context) => void` |

## Lisensi

MIT
