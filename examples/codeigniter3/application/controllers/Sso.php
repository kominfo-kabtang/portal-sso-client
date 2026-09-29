<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\PortalLogin;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;

/**
 * Contoh integrasi Portal ASN untuk CodeIgniter 3 (PHP 7.4+).
 * Salin ke application/controllers/Sso.php lalu sesuaikan loginLocalUser().
 *
 * application/config/config.php:
 *   $config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
 *
 * application/config/routes.php:
 *   $route['sso/login'] = 'sso/login';
 *   $route['callback'] = 'sso/callback';                 // = redirect URI di portal
 *   $route['callback-session'] = 'sso/callback_session'; // dipanggil tile portal
 *   $route['sso/logout'] = 'sso/logout';
 */
class Sso extends CI_Controller
{
    /** Key session untuk token portal (dipakai saat logout). */
    const TOKEN_KEY = 'access_token';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->helper('url');
        $this->config->load('portal_sso');
    }

    public function login()
    {
        try {
            redirect($this->flow()->begin());
        } catch (SsoException $e) {
            $this->failed($e);
        }
    }

    public function callback()
    {
        try {
            $login = $this->flow()->handleCallback((array) $this->input->get());
        } catch (SsoException $e) {
            return $this->failed($e);
        }

        $this->loginLocalUser($login);
    }

    public function callback_session()
    {
        try {
            $login = $this->flow()->handleCallbackSession((array) $this->input->get());
        } catch (SsoException $e) {
            return $this->failed($e);
        }

        $this->loginLocalUser($login);
    }

    public function logout()
    {
        $portalLogoutUrl = $this->flow()->logout($this->session->userdata(self::TOKEN_KEY));

        $this->session->sess_destroy();

        redirect($portalLogoutUrl ?: site_url('login'));
    }

    /**
     * Sesuaikan dengan aplikasi: cari user lokal berdasarkan NIP,
     * tolak bila tidak ada, lalu simpan login ke session.
     */
    private function loginLocalUser(PortalLogin $login)
    {
        $user = $this->db->get_where('users', array('nip' => $login->nip()))->row();
        if (!$user) {
            return $this->failed(new SsoException(SsoException::UNKNOWN_USER));
        }

        $this->session->sess_regenerate(true);
        $this->session->set_userdata(array(
            'user_id' => $user->id,
            'logged_in' => true,
            self::TOKEN_KEY => $login->token,
        ));

        redirect('dashboard');
    }

    private function flow()
    {
        $config = (array) $this->config->item('portal_sso');
        if (empty($config['callback_url'])) {
            $config['callback_url'] = site_url('callback');
        }

        // Session CI3 disimpan di $_SESSION, jadi NativeSessionStore bawaan sudah cocok.
        return SsoFlow::create(new Config($config), null, function ($message, array $context) {
            log_message('error', $message . ' ' . json_encode($context));
        });
    }

    private function failed(SsoException $e)
    {
        if ($e->context()) {
            log_message('error', 'Portal SSO gagal: ' . $e->getMessage() . ' ' . json_encode($e->context()));
        }

        $this->session->set_flashdata('error', $e->getMessage());
        redirect('login');
    }
}
