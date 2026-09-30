<?php

namespace KominfoKabtang\PortalSso\Core;

/**
 * Konfigurasi koneksi ke Portal ASN, tanpa ketergantungan framework.
 *
 * host        : alamat portal untuk panggilan server-ke-server (boleh IP internal).
 * host_domain : alamat portal untuk redirect browser (domain publik).
 * mode        : "legacy" (bawaan, lewat /request + /api/token-user) atau "oauth"
 *               (OAuth2 standar: /oauth/authorize + /oauth/token dengan client milik aplikasi).
 */
class Config
{
    /** @var array */
    private $values;

    public const MODE_LEGACY = 'legacy';
    public const MODE_OAUTH = 'oauth';

    private const DEFAULTS = [
        'mode' => self::MODE_LEGACY,
        'host' => null,
        'host_domain' => null,
        'client_id' => null,
        'client_secret' => null,
        'callback_url' => null,
        'scopes' => 'view-user',
        'timeout' => 15,
        'connect_timeout' => 5,
        'logout_timeout' => 5,
        'verify_ssl' => true,
    ];

    private const ENV = [
        'mode' => 'SSO_MODE',
        'host' => 'SSO_HOST',
        'host_domain' => 'SSO_HOST_DOMAIN',
        'client_id' => 'SSO_CLIENT_ID',
        'client_secret' => 'SSO_CLIENT_SECRET',
        'callback_url' => 'SSO_CLIENT_CALLBACK',
        'scopes' => 'SSO_SCOPES',
        'timeout' => 'SSO_TIMEOUT',
        'connect_timeout' => 'SSO_CONNECT_TIMEOUT',
        'logout_timeout' => 'SSO_LOGOUT_TIMEOUT',
        'verify_ssl' => 'SSO_VERIFY_SSL',
    ];

    public function __construct(array $values)
    {
        $values = array_merge(self::DEFAULTS, array_intersect_key($values, self::DEFAULTS));

        // host_domain jatuh ke host bila kosong, sama seperti config Laravel.
        if (!self::filled($values['host_domain'])) {
            $values['host_domain'] = $values['host'];
        }

        $this->values = $values;
    }

    /**
     * Baca variabel SSO_* dari environment (getenv, $_ENV, $_SERVER).
     * Nilai di $overrides menang atas environment.
     */
    public static function fromEnv(array $overrides = []): self
    {
        $values = [];
        foreach (self::ENV as $key => $name) {
            $value = self::env($name);
            if ($value !== null) {
                $values[$key] = $key === 'verify_ssl' ? self::toBool($value) : $value;
            }
        }

        return new self(array_merge($values, $overrides));
    }

    /**
     * Nilai selain "oauth" dianggap "legacy" agar aplikasi lama tidak berubah perilaku.
     */
    public function mode(): string
    {
        return strtolower(trim((string) $this->values['mode'])) === self::MODE_OAUTH ? self::MODE_OAUTH : self::MODE_LEGACY;
    }

    public function usesOauth(): bool
    {
        return $this->mode() === self::MODE_OAUTH;
    }

    public function host(): string
    {
        return rtrim((string) $this->values['host'], '/');
    }

    public function hostDomain(): string
    {
        return rtrim((string) $this->values['host_domain'], '/');
    }

    public function clientId(): ?string
    {
        return self::filled($this->values['client_id']) ? (string) $this->values['client_id'] : null;
    }

    public function clientSecret(): ?string
    {
        return self::filled($this->values['client_secret']) ? (string) $this->values['client_secret'] : null;
    }

    public function callbackUrl(): string
    {
        return (string) $this->values['callback_url'];
    }

    public function scopes(): ?string
    {
        return self::filled($this->values['scopes']) ? (string) $this->values['scopes'] : null;
    }

    public function timeout(): int
    {
        return (int) $this->values['timeout'];
    }

    public function connectTimeout(): int
    {
        return (int) $this->values['connect_timeout'];
    }

    public function logoutTimeout(): int
    {
        return (int) $this->values['logout_timeout'];
    }

    public function verifySsl(): bool
    {
        return self::toBool($this->values['verify_ssl']);
    }

    /**
     * @param mixed $value
     */
    public static function filled($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'off', 'no', '(false)'], true);
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
