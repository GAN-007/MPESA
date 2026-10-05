<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Http;

use Safaricom\Mpesa\Exception\PaymentException;

final class HttpResponse
{
    public function __construct(public readonly int $status, public readonly array $headers, public readonly string $body) {}
    public function json(): array
    {
        if ($this->body === '') return [];
        try { $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw PaymentException::provider('Expected JSON response but received invalid JSON: ' . $e->getMessage(), $this->status); }
        if (!is_array($decoded)) throw PaymentException::provider('Expected a JSON object or array response.', $this->status);
        return $decoded;
    }
    public function successful(): bool { return $this->status >= 200 && $this->status < 300; }
}
