<?php

namespace KominfoKabtang\PortalSso\Core\Session;

/**
 * Session di memori, untuk test.
 */
class ArraySessionStore implements SessionStore
{
    /** @var array */
    public $data = [];

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function put(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function pull(string $key)
    {
        $value = $this->get($key);
        unset($this->data[$key]);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }
}
