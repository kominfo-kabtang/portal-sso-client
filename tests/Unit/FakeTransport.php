<?php

namespace KominfoKabtang\PortalSso\Tests\Unit;

use KominfoKabtang\PortalSso\Core\Http\HttpResponse;
use KominfoKabtang\PortalSso\Core\Http\Transport;

class FakeTransport implements Transport
{
    /** @var array path => HttpResponse|\Throwable */
    private $routes;

    /** @var array */
    public $sent = [];

    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public function send(string $method, string $url, array $options): HttpResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url] + $options;

        $path = (string) parse_url($url, PHP_URL_PATH);
        $result = $this->routes[$path] ?? new HttpResponse(404, '');
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }

    public static function json(array $data, int $status = 200): HttpResponse
    {
        return new HttpResponse($status, (string) json_encode($data));
    }
}
