<?php

namespace KominfoKabtang\PortalSso;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use KominfoKabtang\PortalSso\Contracts\SettingsResolver;
use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\Http\HttpResponse;
use KominfoKabtang\PortalSso\Core\PortalClient;
use KominfoKabtang\PortalSso\Core\SsoFlow;
use KominfoKabtang\PortalSso\Laravel\LaravelSessionStore;
use KominfoKabtang\PortalSso\Laravel\LaravelTransport;

/**
 * Adaptor Laravel untuk Portal ASN. Protokolnya ada di Core\PortalClient
 * dan Core\SsoFlow; kelas ini mengisi config, session, dan Auth Laravel.
 */
class PortalSsoClient
{
    /** @var SettingsResolver */
    private $settings;

    public function __construct(SettingsResolver $settings)
    {
        $this->settings = $settings;
    }

    public function enabled(): bool
    {
        return $this->settings->enabled();
    }

    public function required(): bool
    {
        return $this->settings->required();
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    public function callbackUrl(): string
    {
        return config('portal-sso.callback_url') ?: route('portal-sso.callback');
    }

    /**
     * Client protokol portal. Dibuat ulang tiap panggilan supaya perubahan
     * config saat runtime (termasuk di test) selalu terbaca.
     */
    public function client(): PortalClient
    {
        $config = new Config([
            'host' => config('portal-sso.host'),
            'host_domain' => config('portal-sso.host_domain'),
            'client_id' => config('portal-sso.client_id'),
            'client_secret' => config('portal-sso.client_secret'),
            'callback_url' => $this->callbackUrl(),
            'scopes' => config('portal-sso.scopes'),
            'timeout' => config('portal-sso.timeout', 15),
            'connect_timeout' => config('portal-sso.connect_timeout', 5),
            'logout_timeout' => config('portal-sso.logout_timeout', 5),
            'verify_ssl' => config('portal-sso.verify_ssl', true),
        ]);

        return new PortalClient($config, new LaravelTransport(), function (string $message, array $context) {
            Log::warning($message, $context);
        });
    }

    public function flow(Request $request): SsoFlow
    {
        return new SsoFlow($this->client(), new LaravelSessionStore($request->session()));
    }

    public function authorizeUrl(string $state): string
    {
        return $this->client()->authorizeUrl($state);
    }

    public function exchangeCode(string $code, string $state): HttpResponse
    {
        return $this->client()->exchangeCode($code, $state);
    }

    public function user(string $token): HttpResponse
    {
        return $this->client()->user($token);
    }

    public function verifyToken(string $token): HttpResponse
    {
        return $this->client()->verifyToken($token);
    }

    public function checkUser(string $token): HttpResponse
    {
        return $this->client()->checkUser($token);
    }

    public function get(string $token, string $path): HttpResponse
    {
        return $this->client()->get($token, $path);
    }

    /**
     * Cabut token di portal. Tidak pernah melempar exception.
     */
    public function revoke(string $token): bool
    {
        return $this->client()->revoke($token);
    }

    /**
     * Logout lokal + cabut token portal. Kegagalan portal tidak boleh
     * menggagalkan logout lokal.
     */
    public function logout(Request $request): RedirectResponse
    {
        $accessToken = $request->session()->get(config('portal-sso.session_token_key', 'access_token'));

        $portalLogoutUrl = $this->flow($request)->logout(is_string($accessToken) ? $accessToken : null);

        Auth::guard(config('portal-sso.guard'))->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $portalLogoutUrl
            ? redirect()->away($portalLogoutUrl)
            : redirect()->to($this->loginUrl());
    }

    public function loginUrl(): string
    {
        $login = (string) config('portal-sso.login_route', 'login');

        return Route::has($login) ? route($login) : url($login);
    }
}
