<?php

namespace KominfoKabtang\PortalSso\Tests\Unit;

use KominfoKabtang\PortalSso\Core\Config;
use KominfoKabtang\PortalSso\Core\PortalClient;
use KominfoKabtang\PortalSso\Core\PortalLogin;
use KominfoKabtang\PortalSso\Core\Session\ArraySessionStore;
use KominfoKabtang\PortalSso\Core\SsoException;
use KominfoKabtang\PortalSso\Core\SsoFlow;
use PHPUnit\Framework\TestCase;

/**
 * Core tanpa framework: dipakai CodeIgniter 3/4 dan PHP native.
 */
class SsoFlowTest extends TestCase
{
    /** @var ArraySessionStore */
    private $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySessionStore();
    }

    private function flow(FakeTransport $transport, array $config = []): SsoFlow
    {
        $config = new Config(array_merge([
            'host' => 'http://10.0.0.1/',
            'host_domain' => 'https://portal.test',
            'client_id' => '7',
            'client_secret' => 'rahasia',
            'callback_url' => 'https://app.test/callback',
        ], $config));

        return new SsoFlow(new PortalClient($config, $transport), $this->session);
    }

    public function test_core_does_not_depend_on_laravel(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src/Core'));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $this->assertStringNotContainsString('Illuminate', (string) file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    public function test_begin_stores_state_and_builds_authorize_url(): void
    {
        $url = $this->flow(new FakeTransport())->begin();

        $state = $this->session->get(SsoFlow::STATE_KEY);
        $this->assertIsString($state);
        $this->assertSame(40, strlen($state));
        $this->assertStringStartsWith('https://portal.test/request?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($state, $query['state']);
        $this->assertSame('7', $query['client_id']);
        $this->assertSame('https://app.test/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('view-user', $query['scope']);
    }

    public function test_begin_fails_when_not_configured(): void
    {
        $this->expectExceptionObject(new SsoException(SsoException::NOT_CONFIGURED));

        $this->flow(new FakeTransport(), ['host' => null, 'host_domain' => null])->begin();
    }

    public function test_host_domain_falls_back_to_host(): void
    {
        $config = new Config(['host' => 'http://portal.local']);

        $this->assertSame('http://portal.local', $config->hostDomain());
    }

    public function test_callback_rejects_invalid_or_reused_state(): void
    {
        $transport = new FakeTransport();
        $flow = $this->flow($transport);
        $this->session->put(SsoFlow::STATE_KEY, 'benar');

        foreach (['salah', 'benar'] as $given) {
            try {
                $flow->handleCallback(['code' => 'abc', 'state' => $given]);
                $this->fail('Harus gagal');
            } catch (SsoException $e) {
                $this->assertSame(SsoException::INVALID_STATE, $e->getMessage());
            }
        }

        $this->assertNull($this->session->get(SsoFlow::STATE_KEY));
        $this->assertSame([], $transport->sent);
    }

    public function test_callback_rejects_array_input(): void
    {
        $this->session->put(SsoFlow::STATE_KEY, 'st');

        $this->expectExceptionObject(new SsoException(SsoException::MISSING_CODE));

        $this->flow(new FakeTransport())->handleCallback(['code' => ['x'], 'state' => 'st']);
    }

    public function test_callback_exchanges_code_and_returns_portal_user(): void
    {
        $transport = new FakeTransport([
            '/api/token-user' => FakeTransport::json(['access_token' => 'tok-123']),
            '/api/user' => FakeTransport::json(['nip' => '199001012020121001', 'name' => 'Portal']),
        ]);
        $this->session->put(SsoFlow::STATE_KEY, 'st');

        $login = $this->flow($transport)->handleCallback(['code' => 'abc', 'state' => 'st']);

        $this->assertSame('tok-123', $login->token);
        $this->assertSame('199001012020121001', $login->nip());
        $this->assertSame(PortalLogin::FLOW_OAUTH, $login->flow);

        $exchange = $transport->sent[0];
        $this->assertSame('POST', $exchange['method']);
        $this->assertSame('http://10.0.0.1/api/token-user', $exchange['url']);
        $this->assertSame('abc', $exchange['form']['code']);
        $this->assertSame('rahasia', $exchange['form']['client_secret']);
        $this->assertSame('https://app.test/callback', $exchange['form']['redirect_uri']);
        $this->assertArrayNotHasKey('url', $exchange['form']);

        $this->assertSame('http://10.0.0.1/api/user', $transport->sent[1]['url']);
        $this->assertSame('Bearer tok-123', $transport->sent[1]['headers']['Authorization']);
    }

    public function test_callback_turns_portal_errors_into_safe_exception(): void
    {
        $cases = [
            'down' => [new \RuntimeException('Connection refused'), ['error' => 'Connection refused']],
            '500' => [FakeTransport::json([], 500), ['http_status' => 500]],
            'no token' => [FakeTransport::json(['foo' => 'bar']), ['http_status' => 200]],
        ];

        foreach ($cases as $name => [$response, $context]) {
            $this->session->put(SsoFlow::STATE_KEY, 'st');
            try {
                $this->flow(new FakeTransport(['/api/token-user' => $response]))
                    ->handleCallback(['code' => 'abc', 'state' => 'st']);
                $this->fail($name);
            } catch (SsoException $e) {
                $this->assertSame(SsoException::EXCHANGE_FAILED, $e->getMessage(), $name);
                $this->assertSame($context, $e->context(), $name);
            }
        }
    }

    public function test_callback_requires_nip(): void
    {
        $this->session->put(SsoFlow::STATE_KEY, 'st');
        $transport = new FakeTransport([
            '/api/token-user' => FakeTransport::json(['access_token' => 'tok']),
            '/api/user' => FakeTransport::json(['name' => 'Tanpa NIP']),
        ]);

        $this->expectExceptionObject(new SsoException(SsoException::MISSING_NIP));

        $this->flow($transport)->handleCallback(['code' => 'abc', 'state' => 'st']);
    }

    public function test_callback_session_verifies_token_then_fetches_user(): void
    {
        $transport = new FakeTransport([
            '/api/verify-token' => FakeTransport::json(['valid' => true]),
            '/api/cek-user' => FakeTransport::json(['nip' => '222']),
        ]);

        $login = $this->flow($transport)->handleCallbackSession(['token' => 'tile-tok']);

        $this->assertSame('tile-tok', $login->token);
        $this->assertSame('222', $login->nip());
        $this->assertSame(PortalLogin::FLOW_PORTAL_TILE, $login->flow);
        $this->assertSame(['http://10.0.0.1/api/verify-token', 'http://10.0.0.1/api/cek-user'], array_column($transport->sent, 'url'));
    }

    public function test_callback_session_rejects_invalid_token(): void
    {
        $transport = new FakeTransport(['/api/verify-token' => FakeTransport::json([], 401)]);

        try {
            $this->flow($transport)->handleCallbackSession(['token' => 'palsu']);
            $this->fail('Harus gagal');
        } catch (SsoException $e) {
            $this->assertSame(SsoException::INVALID_SESSION_TOKEN, $e->getMessage());
        }

        $this->assertCount(1, $transport->sent);
    }

    public function test_logout_revokes_and_returns_portal_url_even_when_portal_down(): void
    {
        $logged = [];
        $config = new Config(['host' => 'http://10.0.0.1', 'host_domain' => 'https://portal.test']);
        $transport = new FakeTransport(['/api/logmeout' => new \RuntimeException('Connection refused')]);
        $client = new PortalClient($config, $transport, function ($message, $context) use (&$logged) {
            $logged[] = $message;
        });

        $url = (new SsoFlow($client, $this->session))->logout('tok');

        $this->assertSame('https://portal.test/logout-api', $url);
        $this->assertSame('Bearer tok', $transport->sent[0]['headers']['Authorization']);
        $this->assertSame(5, $transport->sent[0]['timeout']);
        $this->assertCount(1, $logged);
    }

    public function test_logout_without_token_does_not_call_portal(): void
    {
        $transport = new FakeTransport();

        $this->assertNull($this->flow($transport)->logout(null));
        $this->assertSame([], $transport->sent);
    }

    public function test_config_from_env(): void
    {
        putenv('SSO_HOST=http://env-host');
        putenv('SSO_VERIFY_SSL=false');

        try {
            $config = Config::fromEnv(['callback_url' => 'https://app.test/callback']);

            $this->assertSame('http://env-host', $config->host());
            $this->assertSame('http://env-host', $config->hostDomain());
            $this->assertFalse($config->verifySsl());
            $this->assertSame('https://app.test/callback', $config->callbackUrl());
        } finally {
            putenv('SSO_HOST');
            putenv('SSO_VERIFY_SSL');
        }
    }
}
