<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Status SSO
    |--------------------------------------------------------------------------
    |
    | enabled  : tombol dan rute SSO aktif. Bila false, semua rute SSO 404.
    | required : login lokal pegawai dialihkan ke Portal ASN (lihat middleware
    |            "portal-sso.required"). Tidak berlaku bila enabled = false.
    |
    | Untuk mengatur keduanya dari database, ganti "settings_resolver".
    |
    */

    'enabled' => env('SSO_ENABLED', false),

    'required' => env('SSO_REQUIRED', false),

    /*
    |--------------------------------------------------------------------------
    | Portal ASN
    |--------------------------------------------------------------------------
    |
    | host        : alamat portal untuk panggilan server-ke-server (boleh IP internal).
    | host_domain : alamat portal untuk redirect browser (domain publik).
    | mode        : "legacy" = lewat /request + /api/token-user (client milik pegawai).
    |               "oauth"  = OAuth2 standar (/oauth/authorize + /oauth/token) memakai
    |               client_id dan client_secret aplikasi dari menu admin portal. Callback
    |               harus sama persis dengan URL callback yang didaftarkan di sana.
    |
    */

    'mode' => env('SSO_MODE', 'legacy'),

    'host' => env('SSO_HOST'),

    'host_domain' => env('SSO_HOST_DOMAIN', env('SSO_HOST')),

    'client_id' => env('SSO_CLIENT_ID'),

    'client_secret' => env('SSO_CLIENT_SECRET'),

    // Kosongkan untuk memakai rute callback bawaan paket (APP_URL/callback).
    'callback_url' => env('SSO_CLIENT_CALLBACK'),

    'scopes' => env('SSO_SCOPES', 'view-user'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => env('SSO_TIMEOUT', 15),

    'connect_timeout' => env('SSO_CONNECT_TIMEOUT', 5),

    // Timeout pendek agar logout tidak tertahan bila portal lambat.
    'logout_timeout' => env('SSO_LOGOUT_TIMEOUT', 5),

    // Set false hanya untuk portal lokal dengan sertifikat self-signed.
    'verify_ssl' => env('SSO_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Rute
    |--------------------------------------------------------------------------
    |
    | Path "callback" harus sama dengan redirect URI OAuth client di portal,
    | dan "callback_session" dipanggil tile portal (/go-to-apps) sebagai
    | {url aplikasi}/callback-session, jadi sebaiknya tidak diubah.
    |
    */

    'routes' => [
        'enabled' => true,
        'middleware' => ['web'],
        'login' => 'sso/login',
        'callback' => 'callback',
        'callback_session' => 'callback-session',
        'logout' => 'sso/logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Login lokal
    |--------------------------------------------------------------------------
    |
    | guard                : guard untuk Auth::login (null = guard default).
    | redirect_after_login : tujuan setelah login bila tidak ada URL "intended".
    | login_route          : nama rute atau path halaman login aplikasi.
    | flash_key            : key session untuk pesan gagal di halaman login.
    | local_login_param    : ?local=1 di halaman login melewati mode wajib SSO.
    | session_token_key    : key session tempat access token portal disimpan.
    |
    */

    'guard' => null,

    'redirect_after_login' => '/',

    'login_route' => 'login',

    'flash_key' => 'error',

    'local_login_param' => 'local',

    'session_token_key' => 'access_token',

    /*
    |--------------------------------------------------------------------------
    | Pencocokan user
    |--------------------------------------------------------------------------
    |
    | user_resolver : kelas yang mengimplementasikan
    |                 KominfoKabtang\PortalSso\Contracts\UserResolver.
    |                 Bawaan: cari user berdasarkan NIP.
    |
    | Opsi "user" di bawah hanya dipakai resolver bawaan.
    |
    */

    'user_resolver' => KominfoKabtang\PortalSso\Resolvers\EloquentUserResolver::class,

    'settings_resolver' => KominfoKabtang\PortalSso\Resolvers\ConfigSettingsResolver::class,

    'user' => [
        // null = model dari auth.providers.users.model
        'model' => null,

        'nip_column' => 'nip',

        // true = buat user baru bila NIP belum terdaftar di aplikasi.
        'create_missing' => false,

        // Kolom lokal => key data user dari portal, dipakai saat membuat user.
        'attributes' => [
            'name' => 'name',
        ],

        // Kolom password diisi acak saat membuat user. null = tidak diisi.
        'password_column' => 'password',
    ],

];
