<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Ids;
use Safaricom\Mpesa\Support\Money;

final class PaymentLinkService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $secret,
    ) {
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw PaymentException::configuration('Payment link base URL must be an absolute URL.');
        }
        if (strlen($secret) < 32) {
            throw PaymentException::configuration('Payment link signing secret must be at least 32 characters.');
        }
    }

    public function create(
        string $merchant,
        Money $amount,
        string $reference,
        int $ttlSeconds = 3600,
        array $metadata = [],
        ?int $now = null,
    ): PaymentLink {
        $now ??= time();
        if ($ttlSeconds < 60 || $ttlSeconds > 2592000) {
            throw PaymentException::validation('Payment link TTL must be between 60 seconds and 30 days.');
        }
        if ($merchant === '' || $reference === '') {
            throw PaymentException::validation('Merchant and reference are required.');
        }
        $payload = [
            'v' => 1,
            'merchant' => $merchant,
            'amount_minor' => $amount->minor,
            'currency' => $amount->currency,
            'scale' => $amount->scale,
            'reference' => $reference,
            'expires_at' => $now + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(8)),
            'metadata' => $metadata,
        ];
        $encoded = Ids::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $signature = Ids::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));
        $token = $encoded . '.' . $signature;
        $url = rtrim($this->baseUrl, '/') . '/pay/' . rawurlencode($token);

        return new PaymentLink($token, $url, $merchant, $amount, $reference, $payload['expires_at'], $metadata);
    }

    public function verify(string $token, ?int $now = null): PaymentLink
    {
        $now ??= time();
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if ($encoded === null || $signature === null) {
            throw PaymentException::validation('Malformed payment link token.');
        }
        $expected = Ids::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            throw PaymentException::validation('Invalid payment link signature.');
        }
        try {
            $payload = json_decode(Ids::base64UrlDecode($encoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw PaymentException::validation('Invalid payment link payload.');
        }
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
            throw PaymentException::validation('Unsupported payment link version.');
        }
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt < $now) {
            throw PaymentException::validation('Payment link has expired.');
        }
        $amount = new Money(
            minor: (int) ($payload['amount_minor'] ?? -1),
            currency: (string) ($payload['currency'] ?? ''),
            scale: (int) ($payload['scale'] ?? 2),
        );
        return new PaymentLink(
            token: $token,
            url: rtrim($this->baseUrl, '/') . '/pay/' . rawurlencode($token),
            merchant: (string) ($payload['merchant'] ?? ''),
            amount: $amount,
            reference: (string) ($payload['reference'] ?? ''),
            expiresAt: $expiresAt,
            metadata: is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        );
    }
}
