<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Http;

interface HttpClient
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse;
}
