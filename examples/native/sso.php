<?php

/**
 * Contoh integrasi Portal ASN untuk PHP tanpa framework.
 *
 *   /sso.php?action=login            tombol "Masuk lewat Portal ASN"
 *   /callback                        → sso.php?action=callback (redirect URI di portal)
 *   /callback-session                → sso.php?action=callback-session (tile portal)
 *   /sso.php?action=logout
 *
 * Arahkan /callback dan /callback-session ke file ini lewat rewrite web server.
 */

require __DIR__ . '/vendor/autoload.php';

use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;

session_start();

$appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/');
$flow = SsoFlow::create(Config::fromEnv(['callback_url' => $appUrl . '/callback']));

function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$action = $_GET['action'] ?? ($path === '/callback' ? 'callback' : ($path === '/callback-session' ? 'callback-session' : ''));

try {
    switch ($action) {
        case 'login':
            redirect_to($flow->begin());

        case 'callback':
        case 'callback-session':
            $login = $action === 'callback'
                ? $flow->handleCallback($_GET)
                : $flow->handleCallbackSession($_GET);

            // Sesuaikan: cari user lokal berdasarkan NIP, tolak bila tidak ada.
            $user = find_user_by_nip($login->nip());
            if (!$user) {
                throw new SsoException(SsoException::UNKNOWN_USER);
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['access_token'] = $login->token;
            redirect_to($appUrl . '/');

        case 'logout':
            $portalLogoutUrl = $flow->logout($_SESSION['access_token'] ?? null);
            $_SESSION = [];
            session_destroy();
            redirect_to($portalLogoutUrl ?: $appUrl . '/login.php');
    }
} catch (SsoException $e) {
    error_log('Portal SSO gagal: ' . $e->getMessage() . ' ' . json_encode($e->context()));
    $_SESSION['error'] = $e->getMessage();
    redirect_to($appUrl . '/login.php');
}

http_response_code(404);

/**
 * Ganti dengan query ke database aplikasi.
 */
function find_user_by_nip(string $nip): ?array
{
    return null;
}
