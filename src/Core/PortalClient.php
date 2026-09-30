<?php

namespace KominfoKabtang\PortalSso\Core;

use KominfoKabtang\PortalSso\Core\Http\GuzzleTransport;
use KominfoKabtang\PortalSso\Core\Http\HttpResponse;
use KominfoKabtang\PortalSso\Core\Http\Transport;

/**
 * Client HTTP ke Portal ASN (SSO server berbasis Laravel Passport).
 * Kontrak endpoint: /request dan /api/token-user (mode legacy) atau /oauth/authorize
 * dan /oauth/token (mode oauth), lalu /api/user, /api/cek-user, /api/verify-token,
 * /api/logmeout, /logout-api.
 */
class PortalClient
{
    /** @var Config */
    private $config;

    /** @var Transport */
    private $transport;

    /** @var callable|null fn(string $message, array $context): void */
    private $logger;

    public function __construct(Config $config, ?Transport $transport = null, ?callable $logger = null)
    {
        $this->config = $config;
        $this->transport = $transport ?: new GuzzleTransport();
        $this->logger = $logger;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function isConfigured(): bool
    {
        if ($this->config->host() === '' || $this->config->hostDomain() === '') {
            return false;
        }

        // Mode oauth memakai client milik aplikasi, jadi keduanya wajib.
        return !$this->config->usesOauth()
            || ($this->config->clientId() !== null && $this->config->clientSecret() !== null);
    }

    public function authorizeUrl(string $state): string
    {
        $params = [
            'client_id' => $this->config->clientId(),
            'redirect_uri' => $this->config->callbackUrl(),
            'response_type' => 'code',
            'scope' => $this->config->scopes(),
            'state' => $state,
        ];

        if ($this->config->usesOauth()) {
            return $this->config->hostDomain() . '/oauth/authorize?' . http_build_query(self::filled($params));
        }

        $params['prompt'] = true;

        return $this->config->hostDomain() . '/request?' . http_build_query(self::filled($params));
    }

    /**
     * Tukar code dengan token. Parameter "url" sengaja tidak dikirim
     * (portal mengabaikannya sejak 2026-09-29; lihat kasus SSRF).
     */
    public function exchangeCode(string $code, string $state): HttpResponse
    {
        if ($this->config->usesOauth()) {
            // State sudah dicocokkan SsoFlow; /oauth/token memeriksa client_secret dan redirect_uri.
            return $this->send('POST', '/oauth/token', null, self::filled([
                'grant_type' => 'authorization_code',
                'client_id' => $this->config->clientId(),
                'client_secret' => $this->config->clientSecret(),
                'redirect_uri' => $this->config->callbackUrl(),
                'code' => $code,
            ]));
        }

        return $this->send('POST', '/api/token-user', null, self::filled([
            'state' => $state,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->config->callbackUrl(),
            'code' => $code,
            'client_id' => $this->config->clientId(),
            'client_secret' => $this->config->clientSecret(),
        ]));
    }

    /**
     * Data user dari alur OAuth (/callback).
     */
    public function user(string $token): HttpResponse
    {
        return $this->get($token, '/api/user');
    }

    /**
     * Cek token dari tile portal (/callback-session).
     */
    public function verifyToken(string $token): HttpResponse
    {
        return $this->get($token, '/api/verify-token');
    }

    /**
     * Data user dari token tile portal (/callback-session).
     */
    public function checkUser(string $token): HttpResponse
    {
        return $this->get($token, '/api/cek-user');
    }

    public function get(string $token, string $path): HttpResponse
    {
        return $this->send('GET', $path, $token);
    }

    /**
     * Cabut semua token user di portal. Tidak pernah melempar exception.
     */
    public function revoke(string $token): bool
    {
        try {
            return $this->send('GET', '/api/logmeout', $token, null, $this->config->logoutTimeout())->successful();
        } catch (\Throwable $e) {
            $this->log('Portal SSO logmeout gagal, lanjut logout lokal', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * URL untuk mengakhiri sesi browser di portal (redirect browser).
     */
    public function logoutUrl(): string
    {
        return $this->config->hostDomain() . '/logout-api';
    }

    public function log(string $message, array $context = []): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $message, $context);
        }
    }

    private function send(string $method, string $path, ?string $token = null, ?array $form = null, ?int $timeout = null): HttpResponse
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->transport->send($method, $this->config->host() . $path, [
            'headers' => $headers,
            'form' => $form,
            'timeout' => $timeout !== null ? $timeout : $this->config->timeout(),
            'connect_timeout' => $this->config->connectTimeout(),
            'verify' => $this->config->verifySsl(),
        ]);
    }

    private static function filled(array $values): array
    {
        return array_filter($values, function ($value) {
            return $value === true || Config::filled($value);
        });
    }
}
