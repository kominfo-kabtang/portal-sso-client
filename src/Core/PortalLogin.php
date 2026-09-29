<?php

namespace KominfoKabtang\PortalSso\Core;

/**
 * Hasil login portal yang sudah diverifikasi. Aplikasi tinggal mencocokkan
 * $user (minimal berisi "nip") ke user lokal, lalu menyimpan $token di session.
 */
class PortalLogin
{
    public const FLOW_OAUTH = 'oauth';
    public const FLOW_PORTAL_TILE = 'portal-tile';

    /** @var array Data user dari portal (/api/user atau /api/cek-user). */
    public $user;

    /** @var string Access token portal. Jangan dicatat ke log. */
    public $token;

    /** @var string FLOW_OAUTH atau FLOW_PORTAL_TILE */
    public $flow;

    public function __construct(array $user, string $token, string $flow)
    {
        $this->user = $user;
        $this->token = $token;
        $this->flow = $flow;
    }

    public function nip(): string
    {
        return trim((string) ($this->user['nip'] ?? ''));
    }
}
