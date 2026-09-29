<?php

namespace KominfoKabtang\PortalSso\Core\Session;

/**
 * Penyimpanan session minimal yang dibutuhkan alur SSO (state OAuth).
 */
interface SessionStore
{
    /**
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param mixed $value
     */
    public function put(string $key, $value): void;

    /**
     * Ambil lalu hapus (sekali pakai).
     *
     * @return mixed
     */
    public function pull(string $key);

    public function forget(string $key): void;
}
