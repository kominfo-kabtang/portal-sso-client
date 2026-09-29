<?php

namespace KominfoKabtang\PortalSso\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Dipicu setelah user berhasil login lewat Portal ASN.
 */
class PortalUserLoggedIn
{
    /** @var Authenticatable */
    public $user;

    /** @var array Data user dari portal. */
    public $portalUser;

    public function __construct(Authenticatable $user, array $portalUser)
    {
        $this->user = $user;
        $this->portalUser = $portalUser;
    }
}
