<?php
declare(strict_types=1);

namespace Tests;

use Safaricom\Mpesa\Http\HttpClient;
use Safaricom\Mpesa\Http\HttpResponse;

final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse> */
    private array $queue = [];
    /** @var list<array{method:string,url:string,headers:array,body:?string}> */
    public array $requests = [];

    public function queueJson(int $status, array $body, array $headers = []): void
    {
        $this->queue[] = new HttpResponse($status, $headers, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        if ($this->queue === []) {
            throw new \RuntimeException('No queued fake HTTP response.');
        }
        return array_shift($this->queue);
    }
}
