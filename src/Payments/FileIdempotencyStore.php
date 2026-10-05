<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

/**
 * Durable, dependency-free idempotency storage for a single shared filesystem.
 *
 * For horizontally scaled deployments use the same interface with a database or
 * Redis-backed implementation. This implementation uses per-key flock() locks,
 * fsync() and atomic rename to survive concurrent PHP workers and process crashes.
 */
final class FileIdempotencyStore implements IdempotencyStore
{
    public function __construct(private readonly string $directory)
    {
        if ($directory === '') {
            throw PaymentException::configuration('Idempotency directory is required.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw PaymentException::configuration('Unable to create idempotency directory.');
        }
        if (!is_writable($directory)) {
            throw PaymentException::configuration('Idempotency directory is not writable.');
        }
    }

    public function get(string $key): ?PaymentResult
    {
        $this->assertKey($key);
        return $this->withLock($key, LOCK_SH, function () use ($key): ?PaymentResult {
            $record = $this->readRecord($key);
            return $record === null ? null : $this->hydrateResult($record['result']);
        });
    }

    public function put(string $key, string $fingerprint, PaymentResult $result): void
    {
        $this->assertKey($key);
        $this->assertFingerprint($fingerprint);
        $this->withLock($key, LOCK_EX, function () use ($key, $fingerprint, $result): void {
            $existing = $this->readRecord($key);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['fingerprint'], $fingerprint)) {
                    throw PaymentException::validation('The idempotency key was already used for a different payment request.');
                }
                return;
            }

            $this->writeRecord($key, $fingerprint, $result);
        });
    }

    public function assertSameRequest(string $key, string $fingerprint): void
    {
        $this->assertKey($key);
        $this->assertFingerprint($fingerprint);
        $this->withLock($key, LOCK_SH, function () use ($key, $fingerprint): void {
            $record = $this->readRecord($key);
            if ($record !== null && !hash_equals((string) $record['fingerprint'], $fingerprint)) {
                throw PaymentException::validation('The idempotency key was already used for a different payment request.');
            }
        });
    }

    public function remember(string $key, string $fingerprint, callable $producer): PaymentResult
    {
        $this->assertKey($key);
        $this->assertFingerprint($fingerprint);
        return $this->withLock($key, LOCK_EX, function () use ($key, $fingerprint, $producer): PaymentResult {
            $existing = $this->readRecord($key);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['fingerprint'], $fingerprint)) {
                    throw PaymentException::validation('The idempotency key was already used for a different payment request.');
                }
                return $this->hydrateResult($existing['result']);
            }
            $result = $producer();
            if (!$result instanceof PaymentResult) {
                throw PaymentException::validation('Idempotency producer must return PaymentResult.');
            }
            $this->writeRecord($key, $fingerprint, $result);
            return $result;
        });
    }

    private function readRecord(string $key): ?array
    {
        $path = $this->dataPath($key);
        if (!is_file($path)) {
            return null;
        }
        $payload = file_get_contents($path);
        if ($payload === false || $payload === '') {
            throw PaymentException::transport('Unable to read idempotency record.');
        }
        try {
            $record = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw PaymentException::transport('Corrupt idempotency record: ' . $e->getMessage());
        }
        if (!is_array($record) || !isset($record['fingerprint'], $record['result']) || !is_array($record['result'])) {
            throw PaymentException::transport('Corrupt idempotency record structure.');
        }
        return $record;
    }

    private function writeRecord(string $key, string $fingerprint, PaymentResult $result): void
    {
        $record = [
            'key_hash' => hash('sha256', $key),
            'fingerprint' => $fingerprint,
            'created_at' => time(),
            'result' => [
                'payment_id' => $result->paymentId,
                'provider' => $result->provider,
                'status' => $result->status->value,
                'provider_reference' => $result->providerReference,
                'customer_message' => $result->customerMessage,
                'raw' => $result->raw,
            ],
        ];
        $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $this->dataPath($key);
        $temporary = $path . '.tmp.' . bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw PaymentException::transport('Unable to create idempotency temporary file.');
        }
        try {
            $remaining = $payload;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw PaymentException::transport('Unable to persist idempotency record.');
                }
                $remaining = substr($remaining, $written);
            }
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
        } finally {
            fclose($handle);
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw PaymentException::transport('Unable to atomically publish idempotency record.');
        }
        @chmod($path, 0600);
    }

    private function hydrateResult(array $data): PaymentResult
    {
        try {
            $status = PaymentStatus::from((string) ($data['status'] ?? ''));
        } catch (\ValueError) {
            throw PaymentException::transport('Corrupt idempotency record payment status.');
        }
        return new PaymentResult(
            paymentId: (string) ($data['payment_id'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            status: $status,
            providerReference: isset($data['provider_reference']) ? (string) $data['provider_reference'] : null,
            customerMessage: isset($data['customer_message']) ? (string) $data['customer_message'] : null,
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : [],
        );
    }

    private function withLock(string $key, int $mode, callable $callback): mixed
    {
        $lockPath = $this->lockPath($key);
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw PaymentException::transport('Unable to open idempotency lock file.');
        }
        try {
            if (!flock($lock, $mode)) {
                throw PaymentException::transport('Unable to acquire idempotency lock.');
            }
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function dataPath(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    private function lockPath(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.lock';
    }

    private function assertKey(string $key): void
    {
        if (trim($key) === '' || strlen($key) > 255) {
            throw PaymentException::validation('Idempotency key must contain 1 to 255 characters.');
        }
    }

    private function assertFingerprint(string $fingerprint): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw PaymentException::validation('Idempotency fingerprint must be a SHA-256 hex digest.');
        }
    }
}
