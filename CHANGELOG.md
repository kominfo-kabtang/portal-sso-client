# Changelog

## 1.0.0 - 2026-09-29

Paket Composer `kominfo-kabtang/portal-sso-client` (namespace `KominfoKabtang\PortalSso`) dan paket npm `@kominfo-kabtang/portal-sso-client`.

### Laravel 8–11

- Login SSO Portal ASN: alur tombol aplikasi (`/sso/login` → `/callback`) dan alur tile portal (`/callback-session`).
- Logout yang mencabut token portal (`/api/logmeout`) dan tetap berhasil walau portal tidak terjangkau.
- `UserResolver` dan `SettingsResolver` yang bisa diganti per aplikasi.
- Middleware `portal-sso.required` untuk mode SSO wajib.
- View tombol `portal-sso::button`.
- Event `PortalUserLoggedIn`.

### Tanpa framework (CodeIgniter 3/4, PHP native)

- Core `KominfoKabtang\PortalSso\Core` (`SsoFlow`, `PortalClient`, `Config`) tanpa ketergantungan Laravel. Adaptor Laravel juga memakai core ini.
- `illuminate/*` tidak lagi menjadi dependensi wajib. Di luar Laravel, paket hanya memasang Guzzle.
- Contoh controller CodeIgniter 3, CodeIgniter 4, dan PHP native di `examples/`.

### Node.js

- Paket npm di `js/` untuk backend Node.js 18+, dengan alur dan pesan error yang sama dengan core PHP.

Semua varian memerlukan PHP 7.4+ atau Node.js 18+.
