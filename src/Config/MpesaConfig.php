<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Config;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Env;

final class MpesaConfig
{
    public function __construct(
        public readonly string $environment,
        public readonly string $consumerKey,
        public readonly string $consumerSecret,
        public readonly ?string $shortCode = null,
        public readonly ?string $passkey = null,
        public readonly ?string $initiator = null,
        public readonly ?string $securityCredential = null,
        public readonly ?string $callbackUrl = null,
        public readonly int $timeoutSeconds = 30,
        public readonly int $connectTimeoutSeconds = 10,
        public readonly int $retries = 2,
    ) {
        if (!in_array($environment, ['sandbox', 'live'], true)) {
            throw PaymentException::configuration('MPESA_ENV must be either sandbox or live.');
        }
        if ($consumerKey === '' || $consumerSecret === '') {
            throw PaymentException::configuration('MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET are required.');
        }
        if ($timeoutSeconds < 1 || $connectTimeoutSeconds < 1 || $retries < 0) {
            throw PaymentException::configuration('HTTP timeout values must be positive and retries cannot be negative.');
        }
    }

    public static function fromEnvironment(?string $envPath = null): self
    {
        $paths = array_filter([$envPath, getcwd() . DIRECTORY_SEPARATOR . '.env', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env']);
        foreach ($paths as $path) {
            Env::load((string) $path);
        }
        return new self(
            environment: strtolower((string) Env::get('MPESA_ENV', 'sandbox')),
            consumerKey: (string) Env::get('MPESA_CONSUMER_KEY', ''),
            consumerSecret: (string) Env::get('MPESA_CONSUMER_SECRET', ''),
            shortCode: self::nullable(Env::get('MPESA_SHORTCODE')),
            passkey: self::nullable(Env::get('MPESA_PASSKEY')),
            initiator: self::nullable(Env::get('MPESA_INITIATOR')),
            securityCredential: self::nullable(Env::get('MPESA_SECURITY_CREDENTIAL')),
            callbackUrl: self::nullable(Env::get('MPESA_CALLBACK_URL')),
            timeoutSeconds: max(1, (int) Env::get('MPESA_HTTP_TIMEOUT', '30')),
            connectTimeoutSeconds: max(1, (int) Env::get('MPESA_HTTP_CONNECT_TIMEOUT', '10')),
            retries: max(0, (int) Env::get('MPESA_HTTP_RETRIES', '2')),
        );
    }

    public function baseUrl(): string
    {
        return $this->environment === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    private static function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
