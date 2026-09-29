<?php

namespace KominfoKabtang\PortalSso\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

interface UserResolver
{
    /**
     * Cari (atau buat) user lokal dari data user portal.
     *
     * $portalUser adalah respons /api/user atau /api/cek-user portal,
     * minimal berisi "nip". Kembalikan null bila user tidak boleh masuk.
     */
    public function resolve(array $portalUser, Request $request): ?Authenticatable;
}
