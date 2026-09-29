# Portal SSO Client

[![tests](https://github.com/kominfo-kabtang/portal-sso-client/actions/workflows/tests.yml/badge.svg)](https://github.com/kominfo-kabtang/portal-sso-client/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kominfo-kabtang/portal-sso-client)](https://packagist.org/packages/kominfo-kabtang/portal-sso-client)

Client login SSO **Portal ASN Kabupaten Tangerang** (Laravel Passport). Pasang di aplikasi pemda agar pegawai bisa masuk lewat portal.

| Aplikasi Anda | Paket | Panduan |
|---|---|---|
| Laravel 8–11 | `composer require kominfo-kabtang/portal-sso-client` | [Laravel](#laravel). Rute, middleware, dan tombol terpasang otomatis |
| CodeIgniter 4 | sama | [CodeIgniter 4](#codeigniter-4) |
| CodeIgniter 3 | sama | [CodeIgniter 3](#codeigniter-3) |
| PHP tanpa framework | sama | [PHP native](#php-native) |
| Node.js (Express, Fastify, Next.js, NestJS) | `npm i @kominfo-kabtang/portal-sso-client` | [js/README.md](js/README.md) |
| SPA (Vue, React) | Lewat backend | [SPA](#aplikasi-spa-vue-react) |

Semua versi PHP memerlukan **PHP 7.4 ke atas**. Di luar Laravel, paket ini hanya menarik `guzzlehttp/guzzle` dan tidak memasang komponen Laravel.

## Alur yang didukung

| Alur | Langkah |
|---|---|
| **A. Dari portal** (tile aplikasi) | Portal `/go-to-apps` → `{APP_URL}/callback-session?token=` → paket memanggil `/api/verify-token` dan `/api/cek-user` → login |
| **B. Dari aplikasi** (tombol "Masuk lewat Portal ASN") | `{APP_URL}/sso/login` → portal `/request` → `/oauth/authorize` → `{APP_URL}/callback?code=&state=` → paket menukar code lewat `/api/token-user`, lalu memanggil `/api/user` → login |
| **Logout** | Paket memanggil `/api/logmeout` (mencabut token), aplikasi logout lokal, lalu browser diarahkan ke portal `/logout-api` |

Di framework apa pun, aplikasi harus menyediakan dua path berikut:

- `{APP_URL}/callback`: redirect URI OAuth client di portal.
- `{APP_URL}/callback-session`: path yang dipanggil tile portal.

## Konfigurasi portal

Variabel environment ini sama untuk semua framework:

```dotenv
SSO_ENABLED=true
SSO_REQUIRED=false

# Panggilan server-ke-server (boleh IP internal portal)
SSO_HOST=http://ip-internal-portal
# Redirect browser (domain publik portal)
SSO_HOST_DOMAIN=https://portal-asn.tangerangkab.go.id

SSO_CLIENT_ID=
SSO_CLIENT_SECRET=
# Opsional. Default: {APP_URL}/callback
SSO_CLIENT_CALLBACK=
SSO_SCOPES=view-user
```

Jangan commit `SSO_CLIENT_SECRET`. Paket tidak pernah mencatat secret, code, atau token ke log.

---

## Laravel

```bash
composer require kominfo-kabtang/portal-sso-client
php artisan vendor:publish --tag=portal-sso-config
```

Isi `.env` seperti di atas, lalu hapus rute `/callback`, `/callback-session`, dan controller SSO lama di aplikasi Anda, karena paket sudah mendaftarkannya.

### Rute

| Method | Path | Nama |
|---|---|---|
| GET | `/sso/login` | `portal-sso.login` |
| GET | `/callback` | `portal-sso.callback` |
| GET | `/callback-session` | `portal-sso.callback-session` |
| POST | `/sso/logout` | `portal-sso.logout` |

Path bisa diubah di `config/portal-sso.php` (`routes`). Namun `/callback` harus sama dengan redirect URI OAuth client di portal, dan `/callback-session` dipakai tile portal. Jadi sebaiknya keduanya tidak diubah.

Bila SSO nonaktif (`SSO_ENABLED=false`), semua rute di atas mengembalikan 404.

### Tombol login

```blade
@include('portal-sso::button', ['class' => 'btn btn-primary w-100'])
```

Tombol hanya tampil bila SSO aktif. Ubah tampilannya dengan `php artisan vendor:publish --tag=portal-sso-views`.

Tampilkan pesan gagal SSO di halaman login. Key flash-nya `error`, bisa diganti lewat `flash_key`:

```blade
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
```

### Logout

Arahkan logout aplikasi ke paket, supaya token portal ikut dicabut:

```php
use KominfoKabtang\PortalSso\Facades\PortalSso;

public function logout(Request $request)
{
    return PortalSso::logout($request);
}
```

Cara lain: kirim form `POST` ke `route('portal-sso.logout')`.

### Mencocokkan user

Secara bawaan, user dicari berdasarkan kolom `nip` pada model `auth.providers.users.model`. User yang belum terdaftar **ditolak**. Opsi pengaturannya ada di `config/portal-sso.php`:

```php
'user' => [
    'model' => null,              // null = auth.providers.users.model
    'nip_column' => 'nip',
    'create_missing' => false,    // true = buat user baru dari data portal
    'attributes' => ['name' => 'name'],  // kolom lokal => key data portal
    'password_column' => 'password',     // diisi acak saat membuat user
],
```

Bila aturannya lebih rumit, buat resolver sendiri. Contohnya: role default, tabel pegawai terpisah, atau menolak user nonaktif.

```php
namespace App\Sso;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use KominfoKabtang\PortalSso\Contracts\UserResolver;

class PortalUserResolver implements UserResolver
{
    public function resolve(array $portalUser, Request $request): ?Authenticatable
    {
        return User::firstOrCreate(
            ['nip' => $portalUser['nip']],
            ['nama' => $portalUser['name'] ?? null, 'level' => 'pegawai']
        );
    }
}
```

```php
// config/portal-sso.php
'user_resolver' => App\Sso\PortalUserResolver::class,
```

Setiap login berhasil memicu event `KominfoKabtang\PortalSso\Events\PortalUserLoggedIn`. Event ini membawa `$user` dan `$portalUser`, misalnya untuk menyinkronkan nama atau foto.

### Mode SSO wajib

Dengan `SSO_REQUIRED=true`, halaman login langsung mengalihkan tamu ke portal. Pasang middleware di rute GET halaman login:

```php
Route::get('login', [LoginController::class, 'showLoginForm'])
    ->middleware('portal-sso.required')
    ->name('login');
```

Halaman login tetap bisa dibuka dalam dua kondisi:

- `/login?local=1`, untuk admin atau akun khusus;
- setelah login SSO gagal (ada flash `error`), supaya tidak terjadi redirect berulang.

Middleware ini hanya mengatur halaman login. Bila login lokal pegawai juga harus ditolak, tambahkan pengecekan `PortalSso::required()` di controller login aplikasi.

### Mengatur dari database

Bila status aktif/wajib diatur dari menu admin, buat resolver pengaturan sendiri:

```php
use KominfoKabtang\PortalSso\Contracts\SettingsResolver;

class DatabaseSsoSettings implements SettingsResolver
{
    public function enabled(): bool
    {
        return (bool) (Pengaturan::first()->sso_enabled ?? config('portal-sso.enabled'));
    }

    public function required(): bool
    {
        return $this->enabled() && (bool) (Pengaturan::first()->sso_required ?? config('portal-sso.required'));
    }
}
```

```php
'settings_resolver' => App\Sso\DatabaseSsoSettings::class,
```

### Konfigurasi lain

| Key | Default | Keterangan |
|---|---|---|
| `guard` | `null` | Guard untuk `Auth::login` (null = default) |
| `redirect_after_login` | `/` | Tujuan setelah login bila tidak ada URL *intended* |
| `login_route` | `login` | Nama rute atau path halaman login |
| `session_token_key` | `access_token` | Key session untuk token portal |
| `timeout` / `connect_timeout` / `logout_timeout` | 15 / 5 / 5 | Detik |
| `verify_ssl` | `true` | Set `false` hanya untuk portal lokal dengan sertifikat self-signed |

---

## CodeIgniter dan PHP native

Di luar Laravel, paket ini menyediakan **core** `KominfoKabtang\PortalSso\Core` tanpa ketergantungan framework. Core menangani protokol portal, validasi `state`, dan kegagalan portal. Aplikasi cukup mengerjakan tiga hal: mencocokkan user lokal, menyimpan login ke session, dan redirect.

```php
use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;

$flow = SsoFlow::create(Config::fromEnv(['callback_url' => 'https://app.example/callback']));

$flow->begin();                         // alur B: URL portal untuk redirect (state disimpan di $_SESSION)
$flow->handleCallback($_GET);           // /callback          → PortalLogin
$flow->handleCallbackSession($_GET);    // /callback-session  → PortalLogin
$flow->logout($token);                  // cabut token; URL logout portal atau null
```

`PortalLogin` berisi `$user` (data portal, minimal `nip`), `$token`, `$flow`, dan method `nip()`. Semua kegagalan, termasuk portal yang tidak terjangkau, dilempar sebagai `SsoException`. Pesan `getMessage()` aman ditampilkan ke user, dan `context()` bisa dicatat ke log.

Config bisa diisi dari array (`new Config([...])`) atau dari environment `SSO_*` (`Config::fromEnv()`). Key-nya `host`, `host_domain`, `client_id`, `client_secret`, `callback_url`, `scopes`, `timeout`, `connect_timeout`, `logout_timeout`, dan `verify_ssl`.

### CodeIgniter 4

1. `composer require kominfo-kabtang/portal-sso-client`
2. Isi `.env` dengan variabel `SSO_*` di atas.
3. Salin [`examples/codeigniter4/app/Controllers/Sso.php`](examples/codeigniter4/app/Controllers/Sso.php) ke `app/Controllers/Sso.php`, lalu sesuaikan `loginLocalUser()` (model user, key session, halaman tujuan).
4. Tambahkan rute di `app/Config/Routes.php`:

```php
$routes->get('sso/login', 'Sso::login');
$routes->get('callback', 'Sso::callback');
$routes->get('callback-session', 'Sso::callbackSession');
$routes->post('sso/logout', 'Sso::logout');
```

Tombol login cukup berupa tautan ke `site_url('sso/login')`.

### CodeIgniter 3

1. `composer require kominfo-kabtang/portal-sso-client` di root proyek, lalu aktifkan autoload Composer di `application/config/config.php`:

   ```php
   $config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
   ```

2. Salin [`examples/codeigniter3/application/config/portal_sso.php`](examples/codeigniter3/application/config/portal_sso.php) dan [`examples/codeigniter3/application/controllers/Sso.php`](examples/codeigniter3/application/controllers/Sso.php) ke folder `application/` yang sesuai. Sesuaikan `loginLocalUser()` dengan tabel user aplikasi.
3. Tambahkan rute di `application/config/routes.php`:

```php
$route['sso/login'] = 'sso/login';
$route['callback'] = 'sso/callback';
$route['callback-session'] = 'sso/callback_session';
$route['sso/logout'] = 'sso/logout';
```

CodeIgniter 3 harus berjalan di **PHP 7.4 ke atas**. Session CI3 disimpan di `$_SESSION`, jadi session bawaan core langsung cocok.

### PHP native

Lihat [`examples/native/sso.php`](examples/native/sso.php). Arahkan `/callback` dan `/callback-session` ke file tersebut lewat rewrite web server.

---

## Node.js

Paket npm `@kominfo-kabtang/portal-sso-client` berada di folder [`js/`](js/). Paket ini tanpa dependensi dan memerlukan Node.js 18+. Protokol dan pesan error-nya sama dengan core PHP.

```js
const { createPortalSso, configFromEnv } = require('@kominfo-kabtang/portal-sso-client');

const sso = createPortalSso(configFromEnv(process.env, { callbackUrl: `${APP_URL}/callback` }));
res.redirect(sso.begin(req.session));                       // /sso/login
const login = await sso.handleCallback(req.session, req.query); // /callback
```

Panduan lengkap dan contoh Express ada di [js/README.md](js/README.md).

## Aplikasi SPA (Vue, React)

Alur SSO **harus lewat backend**. Pertukaran code memerlukan `client_secret`, dan token portal tidak boleh disimpan di browser. Karena itu jangan memanggil `/api/token-user` dari JavaScript di browser.

1. Backend SPA (Laravel, CodeIgniter, atau Node) memasang paket ini sesuai panduan di atas.
2. Tombol "Masuk lewat Portal ASN" di SPA hanya berupa tautan biasa ke backend, misalnya `window.location.href = 'https://api.app.example/sso/login'`.
3. Setelah login berhasil, backend membuat sesi aplikasi dengan caranya sendiri, lalu redirect ke SPA. Caranya bisa lewat cookie session (Sanctum SPA) atau kode sekali pakai yang ditukar SPA dengan token aplikasi. Untuk Laravel, lakukan di `UserResolver` atau listener `PortalUserLoggedIn`, lalu atur `redirect_after_login` ke URL SPA.

Jangan meneruskan token portal ke SPA lewat query string.

---

## Migrasi dari integrasi lama

Aplikasi yang sebelumnya memakai `SSOController` salinan (SIMASN, SIPECI):

1. Nama variabel `.env` tetap sama: `SSO_HOST`, `SSO_HOST_DOMAIN`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, `SSO_CLIENT_CALLBACK`. Tambahkan `SSO_ENABLED=true`.
2. Hapus rute `callback`, `callback-session`, dan `sso/login` lama, serta `SSOController`.
3. Hapus `LogoutEventListener` yang memanggil `POST {sso_host}/logmeout`. Rute itu tidak ada di portal; pencabutan token sekarang ditangani `PortalSso::logout()` (Laravel) atau `$flow->logout()` (core).
4. Pindahkan logika pembuatan/pencarian user ke `UserResolver` (Laravel) atau `loginLocalUser()` (CodeIgniter).
5. Parameter `url` ke `/api/token-user` tidak lagi dikirim. Portal sudah mengabaikannya sejak 2026-09-29.

## Pengembangan

```bash
composer install
composer test          # PHP: core (tanpa framework) + Laravel

cd js && npm test      # Node.js
```

Rilis:

- **Packagist**: buat tag `vX.Y.Z`. Packagist mengambil tag secara otomatis.
- **npm**: naikkan `version` di `js/package.json`, lalu jalankan `cd js && npm publish`.

## Lisensi

MIT
