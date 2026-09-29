<?php

namespace KominfoKabtang\PortalSso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use KominfoKabtang\PortalSso\Core\PortalLogin;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;
use KominfoKabtang\PortalSso\Events\PortalUserLoggedIn;
use KominfoKabtang\PortalSso\PortalSsoClient;

class SsoController extends Controller
{
    /** @var PortalSsoClient */
    private $portal;

    public function __construct(PortalSsoClient $portal)
    {
        $this->portal = $portal;
    }

    /**
     * Alur B: tombol "Masuk lewat Portal ASN" → portal /request → /oauth/authorize.
     */
    public function login(Request $request)
    {
        $this->abortUnlessEnabled();

        try {
            return redirect()->away($this->portal->flow($request)->begin());
        } catch (SsoException $e) {
            return $this->failed($request, $e);
        }
    }

    /**
     * Alur B: portal mengembalikan ?code=&state=.
     */
    public function callback(Request $request)
    {
        $this->abortUnlessEnabled();

        try {
            $login = $this->portal->flow($request)->handleCallback($request->query());
        } catch (SsoException $e) {
            return $this->failed($request, $e);
        }

        return $this->loginLocalUser($request, $login);
    }

    /**
     * Alur A: tile aplikasi di Portal ASN (/go-to-apps) → ?token=.
     */
    public function callbackSession(Request $request)
    {
        $this->abortUnlessEnabled();

        try {
            $login = $this->portal->flow($request)->handleCallbackSession($request->query());
        } catch (SsoException $e) {
            return $this->failed($request, $e);
        }

        return $this->loginLocalUser($request, $login);
    }

    public function logout(Request $request)
    {
        return $this->portal->logout($request);
    }

    private function loginLocalUser(Request $request, PortalLogin $login): RedirectResponse
    {
        $user = app(config('portal-sso.user_resolver'))->resolve($login->user, $request);
        if (!$user) {
            return $this->failed($request, new SsoException(SsoException::UNKNOWN_USER));
        }

        Auth::guard(config('portal-sso.guard'))->login($user);
        $request->session()->regenerate();
        $request->session()->put(config('portal-sso.session_token_key', 'access_token'), $login->token);

        event(new PortalUserLoggedIn($user, $login->user));

        return redirect()->intended(config('portal-sso.redirect_after_login', '/'));
    }

    private function abortUnlessEnabled(): void
    {
        abort_unless($this->portal->enabled(), 404);
    }

    private function failed(Request $request, SsoException $e): RedirectResponse
    {
        if ($e->context()) {
            Log::warning('Portal SSO gagal: ' . $e->getMessage(), $e->context());
        }

        $request->session()->forget([SsoFlow::STATE_KEY, config('portal-sso.session_token_key', 'access_token')]);

        // Flash ini juga mencegah middleware "portal-sso.required" redirect ulang ke portal (loop).
        return redirect()->to($this->portal->loginUrl())
            ->with(config('portal-sso.flash_key', 'error'), $e->getMessage());
    }
}
