<?php

namespace App\Controllers;

use App\Models\UserModel;
use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\PortalLogin;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;

/**
 * Contoh integrasi Portal ASN untuk CodeIgniter 4.
 * Salin ke app/Controllers/Sso.php lalu sesuaikan bagian loginLocalUser().
 *
 * app/Config/Routes.php:
 *   $routes->get('sso/login', 'Sso::login');
 *   $routes->get('callback', 'Sso::callback');                 // = redirect URI di portal
 *   $routes->get('callback-session', 'Sso::callbackSession');  // dipanggil tile portal
 *   $routes->post('sso/logout', 'Sso::logout');
 *
 * .env: SSO_HOST, SSO_HOST_DOMAIN, SSO_CLIENT_ID, SSO_CLIENT_SECRET (lihat README).
 */
class Sso extends BaseController
{
    /** Key session untuk token portal (dipakai saat logout). */
    private const TOKEN_KEY = 'access_token';

    public function login()
    {
        try {
            return redirect()->to($this->flow()->begin());
        } catch (SsoException $e) {
            return $this->failed($e);
        }
    }

    public function callback()
    {
        try {
            $login = $this->flow()->handleCallback($this->request->getGet());
        } catch (SsoException $e) {
            return $this->failed($e);
        }

        return $this->loginLocalUser($login);
    }

    public function callbackSession()
    {
        try {
            $login = $this->flow()->handleCallbackSession($this->request->getGet());
        } catch (SsoException $e) {
            return $this->failed($e);
        }

        return $this->loginLocalUser($login);
    }

    public function logout()
    {
        $portalLogoutUrl = $this->flow()->logout(session(self::TOKEN_KEY));

        session()->destroy();

        return redirect()->to($portalLogoutUrl ?: site_url('login'));
    }

    /**
     * Sesuaikan dengan aplikasi: cari user lokal berdasarkan NIP,
     * tolak bila tidak ada, lalu simpan login ke session.
     */
    private function loginLocalUser(PortalLogin $login)
    {
        $user = model(UserModel::class)->where('nip', $login->nip())->first();
        if (!$user) {
            return $this->failed(new SsoException(SsoException::UNKNOWN_USER));
        }

        session()->regenerate(true);
        session()->set([
            'user_id' => $user['id'],
            'logged_in' => true,
            self::TOKEN_KEY => $login->token,
        ]);

        return redirect()->to(site_url('dashboard'));
    }

    private function flow(): SsoFlow
    {
        session(); // mulai session CI4 sebelum SsoFlow memakai $_SESSION

        return SsoFlow::create(
            Config::fromEnv(['callback_url' => site_url('callback')]),
            null,
            function (string $message, array $context) {
                log_message('warning', $message . ' ' . json_encode($context));
            }
        );
    }

    private function failed(SsoException $e)
    {
        if ($e->context()) {
            log_message('warning', 'Portal SSO gagal: ' . $e->getMessage() . ' ' . json_encode($e->context()));
        }

        return redirect()->to(site_url('login'))->with('error', $e->getMessage());
    }
}
