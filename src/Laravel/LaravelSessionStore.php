<?php

namespace KominfoKabtang\PortalSso\Laravel;

use Illuminate\Contracts\Session\Session;
use KominfoKabtang\PortalSso\Core\Session\SessionStore;

class LaravelSessionStore implements SessionStore
{
    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function get(string $key)
    {
        return $this->session->get($key);
    }

    public function put(string $key, $value): void
    {
        $this->session->put($key, $value);
    }

    public function pull(string $key)
    {
        return $this->session->pull($key);
    }

    public function forget(string $key): void
    {
        $this->session->forget($key);
    }
}
