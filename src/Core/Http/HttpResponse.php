<?php

namespace KominfoKabtang\PortalSso\Core\Http;

class HttpResponse
{
    /** @var int */
    private $status;

    /** @var string */
    private $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Body JSON sebagai array, atau satu key-nya. Null bila bukan JSON objek/array.
     *
     * @return mixed
     */
    public function json(?string $key = null)
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            return null;
        }

        return $key === null ? $data : ($data[$key] ?? null);
    }
}
