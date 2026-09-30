<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Konfigurasi Portal ASN untuk CodeIgniter 3.
| Salin ke application/config/portal_sso.php. Jangan commit client_secret;
| isi dari environment server bila memungkinkan.
|
| host        : alamat portal untuk panggilan server-ke-server (boleh IP internal)
| host_domain : alamat portal untuk redirect browser (domain publik)
| callback_url: harus sama dengan redirect URI OAuth client di portal
| mode        : "legacy" (bawaan) atau "oauth" (client milik aplikasi dari admin portal,
|               lihat README bagian "Mode OAuth2 standar")
*/
$config['portal_sso'] = array(
    'mode' => getenv('SSO_MODE') ?: 'legacy',
    'host' => getenv('SSO_HOST') ?: '',
    'host_domain' => getenv('SSO_HOST_DOMAIN') ?: 'https://portal-asn.tangerangkab.go.id',
    'client_id' => getenv('SSO_CLIENT_ID') ?: '',
    'client_secret' => getenv('SSO_CLIENT_SECRET') ?: '',
    'callback_url' => '', // kosong = site_url('callback')
    'scopes' => 'view-user',
    'timeout' => 15,
    'connect_timeout' => 5,
    'logout_timeout' => 5,
    'verify_ssl' => true,
);
