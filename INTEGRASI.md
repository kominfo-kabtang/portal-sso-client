# Panduan Integrasi Login Portal ASN

Panduan langkah demi langkah untuk memasang login SSO Portal ASN di aplikasi baru, atau memindahkan aplikasi lama ke mode OAuth2 standar. Detail per framework ada di [README](README.md). Panduan ini merangkum urutan kerjanya dari awal sampai production.

## Gambaran singkat

Pegawai menekan tombol **Masuk lewat Portal ASN** di aplikasi. Mereka login di portal, lalu kembali ke aplikasi dalam keadaan sudah login.

```
Aplikasi /sso/login
  → Portal /oauth/authorize            (pegawai login di portal)
  → Aplikasi /callback?code=&state=    (paket memeriksa state)
  → Portal /oauth/token                (code ditukar dengan client_id + client_secret aplikasi)
  → Portal /api/user                   (data pegawai, minimal NIP)
  → Aplikasi mencocokkan NIP dengan user lokal → login
```

Paket ini mengerjakan semua langkah protokol di atas. Aplikasi cukup menyediakan dua hal: rute callback, dan cara mencocokkan NIP dengan user lokal.

Ada dua mode:

| Mode | Kapan dipakai |
|---|---|
| `oauth` | **Disarankan untuk semua aplikasi.** Aplikasi punya client_id dan client_secret sendiri, dan portal hanya mengirim code ke URL callback yang terdaftar. |
| `legacy` | Bawaan paket. Hanya untuk aplikasi lama yang belum punya client dari admin portal. |

## Langkah 1: Minta client SSO ke admin portal

Admin portal membuatnya di **Admin → Aplikasi → Edit → Login SSO (OAuth2)**. Siapkan data berikut:

- Nama aplikasi (sudah terdaftar di portal).
- **URL callback**, yaitu `{URL aplikasi}/callback`. Contoh: `https://aplikasi.example/callback`.
  - Harus sama persis dengan yang dikirim aplikasi: skema (`http`/`https`), host, port, dan path. Tanpa garis miring di akhir.
  - Bila aplikasi punya beberapa alamat (misalnya staging dan production), daftarkan semuanya, satu URL per baris.

Admin akan memberikan dua nilai:

| Nilai | Keterangan |
|---|---|
| Client ID | UUID, boleh dicatat di dokumentasi internal aplikasi |
| Client Secret | **Rahasia, hanya ditampilkan sekali.** Kirim lewat jalur aman, jangan lewat grup chat. Bila hilang, admin menekan "Ganti Secret" dan secret lama langsung tidak berlaku. |

## Langkah 2: Pasang paket

| Aplikasi | Perintah |
|---|---|
| Laravel 8–11, CodeIgniter 2/3/4, PHP native (PHP 7.4+) | `composer require kominfo-kabtang/portal-sso-client` |
| Node.js 18+ | `npm i @kominfo-kabtang/portal-sso-client` |

Laravel: jalankan juga `php artisan vendor:publish --tag=portal-sso-config`.

## Langkah 3: Isi konfigurasi

Di `.env` aplikasi:

```dotenv
SSO_ENABLED=true
SSO_MODE=oauth

# Panggilan server-ke-server (boleh alamat internal portal)
SSO_HOST=https://portal-asn.tangerangkab.go.id
# Redirect browser (domain publik portal)
SSO_HOST_DOMAIN=https://portal-asn.tangerangkab.go.id

SSO_CLIENT_ID=<client id dari admin portal>
SSO_CLIENT_SECRET=<client secret dari admin portal>
SSO_CLIENT_CALLBACK=https://aplikasi.example/callback
```

- `SSO_CLIENT_CALLBACK` harus sama persis dengan URL callback yang didaftarkan di Langkah 1.
- Jangan commit `.env` atau secret ke repository.
- Pastikan setiap variabel hanya ditulis sekali di `.env`. Baris ganda, misalnya `SSO_CLIENT_ID=` kosong di atas dan yang terisi di bawah, mudah membingungkan.

Catatan per framework:

| Framework | Yang perlu diperhatikan |
|---|---|
| Laravel | Bila `config/portal-sso.php` di-publish sebelum versi 1.1.0, tambahkan `'mode' => env('SSO_MODE', 'legacy'),`. Setelah mengubah `.env`, jalankan `php artisan config:clear`. |
| CodeIgniter 2/3 | Salin [`examples/codeigniter3/application/config/portal_sso.php`](examples/codeigniter3/application/config/portal_sso.php). File ini membaca `SSO_MODE` dan variabel `SSO_*` lain dari environment server. |
| CodeIgniter 4, PHP native | `Config::fromEnv()` membaca `SSO_MODE` otomatis. |
| Node.js | `configFromEnv(process.env)` membaca `SSO_MODE` otomatis, atau isi opsi `mode: 'oauth'`. |

## Langkah 4: Pasang rute dan pencocokan user

| Framework | Yang dikerjakan | Panduan |
|---|---|---|
| Laravel | Rute `/sso/login`, `/callback`, `/callback-session`, dan `/sso/logout` terpasang otomatis. Tambahkan tombol `@include('portal-sso::button')` di halaman login. Hapus controller SSO lama bila ada. | [README → Laravel](README.md#laravel) |
| CodeIgniter 4 | Salin contoh controller dan tambahkan rute. | [README → CodeIgniter 4](README.md#codeigniter-4) |
| CodeIgniter 3 | Salin config dan controller, aktifkan `composer_autoload`, tambahkan rute. | [README → CodeIgniter 3](README.md#codeigniter-3) |
| CodeIgniter 2 | Seperti CI3, ditambah autoload manual dan adaptor session CI2. | [README → CodeIgniter 2](README.md#codeigniter-2) |
| PHP native | Arahkan `/callback` dan `/callback-session` ke skrip contoh. | [`examples/native/sso.php`](examples/native/sso.php) |
| Node.js | `sso.begin(req.session)` di `/sso/login`, `sso.handleCallback(req.session, req.query)` di `/callback`. | [js/README.md](js/README.md) |
| SPA (Vue, React) | Pasang di backend, jangan di browser. | [README → SPA](README.md#aplikasi-spa-vue-react) |

Pencocokan user memakai **NIP**. Secara bawaan, pegawai yang NIP-nya belum ada di tabel user aplikasi **ditolak** dengan pesan "Akun Anda belum terdaftar di aplikasi ini." Bila aplikasi perlu membuat user otomatis atau memberi role default, ikuti [README → Mencocokkan user](README.md#mencocokkan-user).

## Langkah 5: Uji sebelum production

Lakukan di lingkungan lokal atau staging, dengan client yang callback-nya menunjuk ke alamat tersebut.

1. Buka `{URL aplikasi}/sso/login`. Browser harus diarahkan ke `{portal}/oauth/authorize?client_id=<client id aplikasi>&...`. Bila yang muncul `{portal}/request`, mode oauth belum aktif: cek `SSO_MODE`, versi paket (minimal 1.1.0), dan cache config.
2. Login di portal dengan akun pegawai yang NIP-nya ada di aplikasi. Hasilnya harus kembali ke aplikasi dalam keadaan login.
3. Login dengan akun pegawai yang NIP-nya belum ada di aplikasi. Hasilnya harus ditolak dengan pesan yang jelas, kecuali Anda memang mengaktifkan pembuatan user otomatis.
4. Buka aplikasi dari tile di halaman portal. Harus langsung login lewat `/callback-session`.
5. Logout dari aplikasi. Hasilnya keluar dari aplikasi dan diarahkan ke portal.

## Langkah 6: Production

1. Minta admin portal production membuat client dengan URL callback production (`https://`).
2. Isi `.env` production seperti Langkah 3, lalu deploy aplikasi beserta `composer.lock` atau `package-lock.json` yang baru.
3. Laravel: jalankan `php artisan config:clear` (atau `config:cache`) setelah deploy.
4. Ulangi uji Langkah 5 nomor 1, 2, dan 4 di production.

## Memindahkan aplikasi lama dari mode legacy

Aplikasi yang sudah memakai paket ini dengan mode `legacy` tidak perlu mengubah kode:

1. Minta client SSO ke admin portal (Langkah 1). Callback-nya sama dengan yang sudah dipakai, biasanya `{URL aplikasi}/callback`.
2. Naikkan paket ke versi 1.1.0 atau lebih baru.
3. Tambahkan `SSO_MODE=oauth` dan isi `SSO_CLIENT_ID` serta `SSO_CLIENT_SECRET` dengan nilai dari admin portal. Laravel: tambahkan juga key `mode` di `config/portal-sso.php` (lihat Langkah 3).
4. Uji (Langkah 5).

Untuk kembali ke alur lama, cukup ubah `SSO_MODE=legacy`.

Aplikasi yang masih memakai salinan `SSOController` lama, tanpa paket ini, ikuti dulu [README → Migrasi dari integrasi lama](README.md#migrasi-dari-integrasi-lama).

## Mengatasi masalah

| Gejala | Penyebab | Solusi |
|---|---|---|
| Portal menampilkan `{"error":"invalid_client","error_description":"Client authentication failed"}` setelah klik tombol login | `redirect_uri` yang dikirim aplikasi tidak persis sama dengan URL callback yang terdaftar, atau client_id salah atau sudah dicabut | Samakan `SSO_CLIENT_CALLBACK` dengan URL di admin portal: cek `http`/`https`, `www`, port, path, dan garis miring di akhir. Cek juga `SSO_CLIENT_ID`. |
| Aplikasi: "Gagal menukar kode SSO dengan token Portal ASN." dengan log `{"http_status":401}` | Client secret salah atau sudah diganti | Isi ulang `SSO_CLIENT_SECRET`. Bila secret hilang, minta admin menekan "Ganti Secret". |
| Aplikasi: "Konfigurasi SSO Portal ASN belum lengkap." | `SSO_HOST`, `SSO_CLIENT_ID`, atau `SSO_CLIENT_SECRET` kosong di mode oauth | Lengkapi `.env`, lalu bersihkan cache config. |
| Aplikasi: "State SSO tidak valid. Silakan ulangi login." | Session hilang antara `/sso/login` dan `/callback`, tombol Back ditekan, atau domain cookie berbeda | Mulai lagi dari tombol login. Pastikan aplikasi dibuka di domain yang sama dengan URL callback. |
| Aplikasi: "Akun Anda belum terdaftar di aplikasi ini." | NIP pegawai tidak ada di tabel user aplikasi | Daftarkan user, atau atur pembuatan user otomatis. |
| Halaman callback 404 | URL callback terdaftar di portal, tetapi rutenya tidak ada di aplikasi | Pakai `{URL aplikasi}/callback`, yaitu rute bawaan paket. Jangan mendaftarkan path lain. |
| Masih diarahkan ke `{portal}/request` | Mode oauth belum aktif | Cek `SSO_MODE=oauth`, versi paket 1.1.0 atau lebih baru, key `mode` di config Laravel, dan cache config. |

## Keamanan

- Client secret hanya disimpan di `.env` server. Jangan di repository, frontend, atau aplikasi mobile.
- Paket tidak pernah mencatat secret, code, atau token ke log. Jangan menambahkannya di kode aplikasi.
- Gunakan `https` untuk URL callback production.
- Bila secret diduga bocor, minta admin portal menekan **Ganti Secret**, lalu perbarui `.env`.
- Bila aplikasi tidak dipakai lagi, minta admin menekan **Cabut Client**. Semua token milik aplikasi itu ikut dicabut.
