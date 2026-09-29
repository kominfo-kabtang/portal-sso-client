<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\PortalLogin;
use KominfoKabtang\PortalSso\Core\Session\SessionStore;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;

/**
 * Contoh integrasi Portal ASN untuk CodeIgniter 2 (wajib PHP 7.4+).
 * Salin ke application/controllers/sso.php lalu sesuaikan loginLocalUser().
 *
 * CI2 tidak punya opsi composer_autoload. Tambahkan di index.php, tepat
 * sebelum baris require_once BASEPATH.'core/CodeIgniter.php':
 *   require_once __DIR__.'/vendor/autoload.php';
 *
 * application/config/config.php:
 *   $config['allow_get_array'] = TRUE;
 *   $config['sess_use_database'] = TRUE; // cookie hanya berisi session id, bukan token
 *
 * application/config/routes.php:
 *   $route['sso/login'] = 'sso/login';
 *   $route['callback'] = 'sso/callback';                 // = redirect URI di portal
 *   $route['callback-session'] = 'sso/callback_session'; // dipanggil tile portal
 *   $route['sso/logout'] = 'sso/logout';
 *
 * Konfigurasi portal: salin examples/codeigniter3/application/config/portal_sso.php
 * (formatnya sama untuk CI2).
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
        $portalLogoutUrl = $this->flow()->logout($this->session->userdata(self::TOKEN_KEY) ?: null);

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

        // Session CI2 tidak memakai $_SESSION, jadi state disimpan lewat library session CI2.
        return SsoFlow::create(new Config($config), new Ci2SessionStore($this->session), function ($message, array $context) {
            log_message('error', $message . ' ' . json_encode($context));
        });
    }

    private function failed(SsoException $e)
    {
        if ($e->context()) {
            log_message('error', 'Portal SSO gagal: ' . $e->getMessage() . ' ' . json_encode($e->context()));
        }

        $this->session->unset_userdata(SsoFlow::STATE_KEY);
        $this->session->set_flashdata('error', $e->getMessage());
        redirect('login');
    }
}

/**
 * Adaptor library session CI2 (cookie/database) ke SessionStore core.
 * userdata() CI2 mengembalikan FALSE untuk key yang tidak ada.
 */
class Ci2SessionStore implements SessionStore
{
    private $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function get(string $key)
    {
        $value = $this->session->userdata($key);

        return $value === false ? null : $value;
    }

    public function put(string $key, $value): void
    {
        $this->session->set_userdata($key, $value);
    }

    public function pull(string $key)
    {
        $value = $this->get($key);
        $this->session->unset_userdata($key);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->session->unset_userdata($key);
    }
}
