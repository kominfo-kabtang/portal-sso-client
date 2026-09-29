<?php

namespace KominfoKabtang\PortalSso\Core\Http;

/**
 * Pengirim request HTTP ke portal. Bawaan: GuzzleTransport.
 * Adaptor framework boleh memakai client HTTP miliknya sendiri
 * (misalnya Laravel memakai facade Http agar bisa di-fake di test).
 */
interface Transport
{
    /**
     * Kirim request. Kegagalan jaringan boleh melempar exception apa saja;
     * status 4xx/5xx harus dikembalikan sebagai HttpResponse, bukan exception.
     *
     * $options:
     *   headers         array<string,string>
     *   form            array|null   body application/x-www-form-urlencoded
     *   timeout         int          detik
     *   connect_timeout int          detik
     *   verify          bool         verifikasi sertifikat TLS
     */
    public function send(string $method, string $url, array $options): HttpResponse;
}
