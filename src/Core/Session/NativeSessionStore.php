<?php

namespace KominfoKabtang\PortalSso\Core\Session;

/**
 * Session PHP native ($_SESSION). Cocok untuk CodeIgniter 3/4 (keduanya
 * menyimpan session di $_SESSION) dan PHP tanpa framework.
 *
 * Mulai session framework lebih dulu (CI3: $this->load->library('session'),
 * CI4: session()). Bila belum ada session aktif, kelas ini memanggil
 * session_start() sendiri.
 */
class NativeSessionStore implements SessionStore
{
    public function get(string $key)
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function put(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function pull(string $key)
    {
        $value = $this->get($key);
        unset($_SESSION[$key]);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
