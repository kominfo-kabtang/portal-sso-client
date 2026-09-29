<?php

namespace KominfoKabtang\PortalSso\Core\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class GuzzleTransport implements Transport
{
    /** @var ClientInterface */
    private $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?: new Client();
    }

    public function send(string $method, string $url, array $options): HttpResponse
    {
        $request = [
            'headers' => $options['headers'] ?? [],
            'timeout' => $options['timeout'] ?? 15,
            'connect_timeout' => $options['connect_timeout'] ?? 5,
            'verify' => $options['verify'] ?? true,
            'http_errors' => false,
            'allow_redirects' => false,
        ];

        if (isset($options['form'])) {
            $request['form_params'] = $options['form'];
        }

        $response = $this->client->request($method, $url, $request);

        return new HttpResponse($response->getStatusCode(), (string) $response->getBody());
    }
}
