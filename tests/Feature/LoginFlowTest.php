<?php

namespace KominfoKabtang\PortalSso\Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use KominfoKabtang\PortalSso\Contracts\UserResolver;
use KominfoKabtang\PortalSso\Events\PortalUserLoggedIn;
use KominfoKabtang\PortalSso\Tests\Fixtures\User;
use KominfoKabtang\PortalSso\Tests\TestCase;

class LoginFlowTest extends TestCase
{
    public function test_routes_are_registered(): void
    {
        foreach (['portal-sso.login', 'portal-sso.callback', 'portal-sso.callback-session', 'portal-sso.logout'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
        $this->assertSame('http://localhost/callback', route('portal-sso.callback'));
        $this->assertSame('http://localhost/callback-session', route('portal-sso.callback-session'));
    }

    public function test_routes_return_404_when_disabled(): void
    {
        config(['portal-sso.enabled' => false]);

        $this->get('/sso/login')->assertNotFound();
        $this->get('/callback?code=x&state=y')->assertNotFound();
        $this->get('/callback-session?token=x')->assertNotFound();
    }

    public function test_login_redirects_to_portal_with_state(): void
    {
        $response = $this->get('/sso/login');

        $state = session('portal_sso_state');
        $this->assertIsString($state);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://portal.test/request?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($state, $query['state']);
        $this->assertSame('7', $query['client_id']);
        $this->assertSame('http://localhost/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('view-user', $query['scope']);
    }

    public function test_login_fails_gracefully_when_not_configured(): void
    {
        config(['portal-sso.host' => null]);

        $this->get('/sso/login')
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('error', 'Konfigurasi SSO Portal ASN belum lengkap.');
    }

    public function test_callback_rejects_invalid_state(): void
    {
        Http::fake();

        $this->withSession(['portal_sso_state' => 'benar'])
            ->get('/callback?code=abc&state=salah')
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_callback_logs_in_existing_user(): void
    {
        Event::fake([PortalUserLoggedIn::class]);
        $user = User::create(['nip' => '199001012020121001', 'name' => 'Lokal']);

        Http::fake([
            '10.0.0.1/api/token-user' => Http::response(['access_token' => 'tok-123']),
            '10.0.0.1/api/user' => Http::response(['nip' => '199001012020121001', 'name' => 'Portal']),
        ]);

        $this->withSession(['portal_sso_state' => 'st'])
            ->get('/callback?code=abc&state=st')
            ->assertRedirect('http://localhost/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('tok-123', session('access_token'));
        Event::assertDispatched(PortalUserLoggedIn::class);

        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === 'http://10.0.0.1/api/token-user'
                && $request['code'] === 'abc'
                && $request['client_secret'] === 'rahasia'
                && $request['redirect_uri'] === 'http://localhost/callback'
                && !isset($request['url']);
        });
        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === 'http://10.0.0.1/api/user'
                && $request->hasHeader('Authorization', 'Bearer tok-123');
        });
    }

    public function test_callback_rejects_unknown_user_by_default(): void
    {
        Http::fake([
            '10.0.0.1/api/token-user' => Http::response(['access_token' => 'tok']),
            '10.0.0.1/api/user' => Http::response(['nip' => '111', 'name' => 'Baru']),
        ]);

        $this->withSession(['portal_sso_state' => 'st'])
            ->get('/callback?code=abc&state=st')
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('error', 'Akun Anda belum terdaftar di aplikasi ini.');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_callback_creates_user_when_enabled(): void
    {
        config(['portal-sso.user.create_missing' => true]);

        Http::fake([
            '10.0.0.1/api/token-user' => Http::response(['access_token' => 'tok']),
            '10.0.0.1/api/user' => Http::response(['nip' => '111', 'name' => 'Baru']),
        ]);

        $this->withSession(['portal_sso_state' => 'st'])
            ->get('/callback?code=abc&state=st')
            ->assertRedirect('http://localhost/dashboard');

        $user = User::where('nip', '111')->firstOrFail();
        $this->assertSame('Baru', $user->name);
        $this->assertNotEmpty($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_handles_portal_down_without_500(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });

        $this->withSession(['portal_sso_state' => 'st'])
            ->get('/callback?code=abc&state=st')
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('error', 'Gagal menukar kode SSO dengan token Portal ASN.');

        $this->assertGuest();
    }

    public function test_callback_session_logs_in_from_portal_tile(): void
    {
        $user = User::create(['nip' => '222']);

        Http::fake([
            '10.0.0.1/api/verify-token' => Http::response(['valid' => true]),
            '10.0.0.1/api/cek-user' => Http::response(['nip' => '222']),
        ]);

        $this->get('/callback-session?token=tile-tok')
            ->assertRedirect('http://localhost/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('tile-tok', session('access_token'));
    }

    public function test_callback_session_rejects_invalid_token(): void
    {
        User::create(['nip' => '222']);

        Http::fake(['10.0.0.1/api/verify-token' => Http::response([], 401)]);

        $this->get('/callback-session?token=palsu')
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('error', 'Token session Portal ASN tidak valid.');

        $this->assertGuest();
    }

    public function test_custom_user_resolver_is_used(): void
    {
        $user = User::create(['nip' => '333']);
        $this->app->instance('resolver.test', new class($user) implements UserResolver {
            private $user;

            public function __construct($user)
            {
                $this->user = $user;
            }

            public function resolve(array $portalUser, Request $request): ?\Illuminate\Contracts\Auth\Authenticatable
            {
                return $portalUser['nip'] === 'khusus' ? $this->user : null;
            }
        });
        config(['portal-sso.user_resolver' => 'resolver.test']);

        Http::fake([
            '10.0.0.1/api/verify-token' => Http::response([]),
            '10.0.0.1/api/cek-user' => Http::response(['nip' => 'khusus']),
        ]);

        $this->get('/callback-session?token=t')->assertRedirect('http://localhost/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
