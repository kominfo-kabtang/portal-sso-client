<?php

namespace KominfoKabtang\PortalSso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KominfoKabtang\PortalSso\PortalSsoClient;

/**
 * Pasang di rute GET halaman login. Bila SSO wajib, tamu langsung dialihkan
 * ke Portal ASN, kecuali ?local=1 atau baru saja gagal login SSO.
 */
class RedirectToPortalWhenRequired
{
    /** @var PortalSsoClient */
    private $portal;

    public function __construct(PortalSsoClient $portal)
    {
        $this->portal = $portal;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->portal->required()
            && $request->isMethod('GET')
            && !$request->boolean(config('portal-sso.local_login_param', 'local'))
            && !$request->session()->has(config('portal-sso.flash_key', 'error'))) {
            return redirect()->route('portal-sso.login');
        }

        return $next($request);
    }
}
