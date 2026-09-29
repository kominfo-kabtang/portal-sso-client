<?php

namespace KominfoKabtang\PortalSso\Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use KominfoKabtang\PortalSso\Tests\Fixtures\User;
use KominfoKabtang\PortalSso\Tests\TestCase;

class LogoutAndRequiredModeTest extends TestCase
{
    public function test_logout_revokes_token_and_redirects_to_portal(): void
    {
        Http::fake(['10.0.0.1/api/logmeout' => Http::response(['message' => 'ok'])]);
        $user = User::create(['nip' => '1']);

        $this->actingAs($user)
            ->withSession(['access_token' => 'tok'])
            ->post('/sso/logout')
            ->assertRedirect('https://portal.test/logout-api');

        $this->assertGuest();
        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === 'http://10.0.0.1/api/logmeout'
                && $request->hasHeader('Authorization', 'Bearer tok');
        });
    }

    public function test_logout_still_logs_out_when_portal_is_down(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });
        $user = User::create(['nip' => '1']);

        $this->actingAs($user)
            ->withSession(['access_token' => 'tok'])
            ->post('/sso/logout')
            ->assertRedirect('https://portal.test/logout-api');

        $this->assertGuest();
    }

    public function test_logout_without_portal_token_goes_to_local_login(): void
    {
        Http::fake();
        $user = User::create(['nip' => '1']);

        $this->actingAs($user)
            ->post('/sso/logout')
            ->assertRedirect('http://localhost/login');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_login_page_is_shown_when_sso_not_required(): void
    {
        $this->get('/login')->assertOk()->assertSee('halaman login');
    }

    public function test_login_page_redirects_to_portal_when_required(): void
    {
        config(['portal-sso.required' => true]);

        $this->get('/login')->assertRedirect('http://localhost/sso/login');
    }

    public function test_required_mode_allows_local_login_and_failed_sso(): void
    {
        config(['portal-sso.required' => true]);

        $this->get('/login?local=1')->assertOk();
        $this->withSession(['error' => 'gagal'])->get('/login')->assertOk();
    }

    public function test_required_has_no_effect_when_sso_disabled(): void
    {
        config(['portal-sso.enabled' => false, 'portal-sso.required' => true]);

        $this->get('/login')->assertOk();
    }

    public function test_button_view_renders_only_when_enabled(): void
    {
        $html = view('portal-sso::button', ['class' => 'btn-x'])->render();
        $this->assertStringContainsString('href="http://localhost/sso/login"', $html);
        $this->assertStringContainsString('btn-x', $html);

        config(['portal-sso.enabled' => false]);
        $this->assertSame('', trim(view('portal-sso::button')->render()));
    }
}
