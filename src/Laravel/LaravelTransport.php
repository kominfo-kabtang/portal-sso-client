<?php

namespace KominfoKabtang\PortalSso\Laravel;

use Illuminate\Support\Facades\Http;
use KominfoKabtang\PortalSso\Core\Http\HttpResponse;
use KominfoKabtang\PortalSso\Core\Http\Transport;

/**
 * Transport lewat facade Http Laravel, sehingga Http::fake() berlaku di test.
 */
class LaravelTransport implements Transport
{
    public function send(string $method, string $url, array $options): HttpResponse
    {
        $request = Http::withHeaders($options['headers'] ?? [])
            ->withOptions([
                'verify' => $options['verify'] ?? true,
                'connect_timeout' => $options['connect_timeout'] ?? 5,
            ])
            ->timeout($options['timeout'] ?? 15);

        // Portal hanya memakai GET dan POST form.
        $response = strtoupper($method) === 'POST'
            ? $request->asForm()->post($url, $options['form'] ?? [])
            : $request->get($url);

        return new HttpResponse($response->status(), $response->body());
    }
}
