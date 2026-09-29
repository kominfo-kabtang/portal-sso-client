<?php

namespace KominfoKabtang\PortalSso\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use KominfoKabtang\PortalSso\Contracts\UserResolver;

/**
 * Mencocokkan user portal ke model user aplikasi berdasarkan NIP.
 */
class EloquentUserResolver implements UserResolver
{
    public function resolve(array $portalUser, Request $request): ?Authenticatable
    {
        $nip = trim((string) ($portalUser['nip'] ?? ''));
        if ($nip === '') {
            return null;
        }

        $model = config('portal-sso.user.model') ?: config('auth.providers.users.model');
        $column = config('portal-sso.user.nip_column', 'nip');

        $user = $model::query()->where($column, $nip)->first();
        if ($user || !config('portal-sso.user.create_missing', false)) {
            return $user;
        }

        $attributes = [$column => $nip];
        foreach ((array) config('portal-sso.user.attributes', []) as $localColumn => $portalKey) {
            $attributes[$localColumn] = data_get($portalUser, $portalKey);
        }

        $passwordColumn = config('portal-sso.user.password_column', 'password');
        if ($passwordColumn) {
            $attributes[$passwordColumn] = Hash::make(Str::random(64));
        }

        $user = new $model();
        $user->forceFill($attributes)->save();

        Log::info('Portal SSO membuat user lokal baru', ['nip' => $nip]);

        return $user;
    }
}
