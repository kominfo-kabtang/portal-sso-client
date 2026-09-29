<?php

namespace KominfoKabtang\PortalSso\Facades;

use Illuminate\Support\Facades\Facade;
use KominfoKabtang\PortalSso\PortalSsoClient;

/**
 * @method static bool enabled()
 * @method static bool required()
 * @method static bool isConfigured()
 * @method static bool revoke(string $token)
 * @method static \Illuminate\Http\RedirectResponse logout(\Illuminate\Http\Request $request)
 * @method static \KominfoKabtang\PortalSso\Core\Http\HttpResponse get(string $token, string $path)
 * @method static \KominfoKabtang\PortalSso\Core\PortalClient client()
 * @method static \KominfoKabtang\PortalSso\Core\SsoFlow flow(\Illuminate\Http\Request $request)
 *
 * @see PortalSsoClient
 */
class PortalSso extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PortalSsoClient::class;
    }
}
