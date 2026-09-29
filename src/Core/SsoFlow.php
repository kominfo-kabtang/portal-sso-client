<?php

namespace KominfoKabtang\PortalSso\Core;

use KominfoKabtang\PortalSso\Core\Http\HttpResponse;
use KominfoKabtang\PortalSso\Core\Session\NativeSessionStore;
use KominfoKabtang\PortalSso\Core\Session\SessionStore;

/**
 * Alur login Portal ASN tanpa ketergantungan framework.
 *
 *   Alur B (tombol di aplikasi): begin() → redirect → handleCallback($_GET)
 *   Alur A (tile di portal)    : handleCallbackSession($_GET)
 *   Logout                     : logout($token) → redirect ke URL hasilnya
 *
 * Semua kegagalan (termasuk portal mati) dilempar sebagai SsoException
 * dengan pesan yang aman ditampilkan. Mencocokkan user lokal, menyimpan
 * login, dan redirect adalah urusan aplikasi/adaptor.
 */
class SsoFlow
{
    public const STATE_KEY = 'portal_sso_state';

    /** @var PortalClient */
    private $client;

    /** @var SessionStore */
    private $session;

    public function __construct(PortalClient $client, SessionStore $session)
    {
        $this->client = $client;
        $this->session = $session;
    }

    /**
     * Pintasan untuk aplikasi non-Laravel: Guzzle + $_SESSION.
     */
    public static function create(Config $config, ?SessionStore $session = null, ?callable $logger = null): self
    {
        return new self(new PortalClient($config, null, $logger), $session ?: new NativeSessionStore());
    }

    public function client(): PortalClient
    {
        return $this->client;
    }

    /**
     * Alur B langkah 1: simpan state, kembalikan URL portal untuk redirect.
     */
    public function begin(): string
    {
        if (!$this->client->isConfigured()) {
            throw new SsoException(SsoException::NOT_CONFIGURED);
        }

        $state = bin2hex(random_bytes(20));
        $this->session->put(self::STATE_KEY, $state);

        return $this->client->authorizeUrl($state);
    }

    /**
     * Alur B langkah 2: portal kembali ke /callback?code=&state=.
     *
     * @param array $query Query string request (mis. $_GET).
     */
    public function handleCallback(array $query): PortalLogin
    {
        // State sekali pakai: selalu dihapus, valid atau tidak.
        $state = $this->session->pull(self::STATE_KEY);
        $given = isset($query['state']) && is_string($query['state']) ? $query['state'] : '';
        if (!is_string($state) || $state === '' || !hash_equals($state, $given)) {
            throw new SsoException(SsoException::INVALID_STATE);
        }

        $code = isset($query['code']) && is_string($query['code']) ? $query['code'] : '';
        if ($code === '') {
            throw new SsoException(SsoException::MISSING_CODE);
        }

        $client = $this->client;
        $response = $this->call(SsoException::EXCHANGE_FAILED, function () use ($client, $code, $state) {
            return $client->exchangeCode($code, $state);
        });
        $token = $response->json('access_token');
        if (!is_string($token) || $token === '') {
            throw new SsoException(SsoException::EXCHANGE_FAILED, ['http_status' => $response->status()]);
        }

        $user = $this->call(SsoException::USER_FAILED, function () use ($client, $token) {
            return $client->user($token);
        });

        return $this->login($user, $token, PortalLogin::FLOW_OAUTH);
    }

    /**
     * Alur A: tile aplikasi di portal (/go-to-apps) → /callback-session?token=.
     *
     * @param array $query Query string request (mis. $_GET).
     */
    public function handleCallbackSession(array $query): PortalLogin
    {
        $token = isset($query['token']) && is_string($query['token']) ? $query['token'] : '';
        if ($token === '') {
            throw new SsoException(SsoException::MISSING_SESSION_TOKEN);
        }

        $client = $this->client;
        $this->call(SsoException::INVALID_SESSION_TOKEN, function () use ($client, $token) {
            return $client->verifyToken($token);
        });

        $user = $this->call(SsoException::USER_FAILED, function () use ($client, $token) {
            return $client->checkUser($token);
        });

        return $this->login($user, $token, PortalLogin::FLOW_PORTAL_TILE);
    }

    /**
     * Cabut token portal (tidak pernah gagal) dan kembalikan URL logout
     * portal untuk redirect browser, atau null bila tidak perlu ke portal.
     * Logout lokal tetap dikerjakan aplikasi, apa pun hasilnya.
     */
    public function logout(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        if ($this->client->config()->host() !== '') {
            $this->client->revoke($token);
        }

        // Sesi browser portal hanya bisa diakhiri lewat redirect browser.
        return $this->client->config()->hostDomain() !== '' ? $this->client->logoutUrl() : null;
    }

    private function login(HttpResponse $response, string $token, string $flow): PortalLogin
    {
        $user = $response->json();
        if (!is_array($user) || trim((string) ($user['nip'] ?? '')) === '') {
            throw new SsoException(SsoException::MISSING_NIP);
        }

        return new PortalLogin($user, $token, $flow);
    }

    /**
     * Jalankan request ke portal; exception jaringan dan status non-2xx
     * menjadi SsoException dengan $message.
     */
    private function call(string $message, callable $request): HttpResponse
    {
        try {
            $response = $request();
        } catch (\Throwable $e) {
            throw new SsoException($message, ['error' => $e->getMessage()], $e);
        }

        if (!$response->successful()) {
            throw new SsoException($message, ['http_status' => $response->status()]);
        }

        return $response;
    }
}
