<?php

namespace KominfoKabtang\PortalSso\Core;

/**
 * Login SSO gagal. getMessage() aman ditampilkan ke user; context() untuk
 * log dan tidak pernah memuat secret, code, atau token.
 */
class SsoException extends \RuntimeException
{
    public const NOT_CONFIGURED = 'Konfigurasi SSO Portal ASN belum lengkap.';
    public const INVALID_STATE = 'State SSO tidak valid. Silakan ulangi login.';
    public const MISSING_CODE = 'Kode otorisasi SSO tidak ditemukan.';
    public const EXCHANGE_FAILED = 'Gagal menukar kode SSO dengan token Portal ASN.';
    public const USER_FAILED = 'Gagal mengambil data pengguna dari Portal ASN.';
    public const MISSING_SESSION_TOKEN = 'Token session Portal ASN tidak ditemukan.';
    public const INVALID_SESSION_TOKEN = 'Token session Portal ASN tidak valid.';
    public const MISSING_NIP = 'Data pengguna Portal ASN tidak memuat NIP.';

    /** Dipakai adaptor/aplikasi saat UserResolver menolak user. */
    public const UNKNOWN_USER = 'Akun Anda belum terdaftar di aplikasi ini.';

    /** @var array */
    private $context;

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    public function context(): array
    {
        return $this->context;
    }
}
