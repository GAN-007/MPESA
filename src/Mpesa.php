<?php

declare(strict_types=1);

namespace Safaricom\Mpesa;

use RuntimeException;

class Mpesa
{
    private string $environment;
    private string $consumerKey;
    private string $consumerSecret;
    private int $timeout;
    private bool $verifySsl;

    public function __construct(?array $config = null)
    {
        $config = $config ?? [];
        $this->environment = strtolower((string)($config['environment'] ?? self::env('MPESA_ENV', 'sandbox')));
        $this->consumerKey = (string)($config['consumer_key'] ?? self::env('MPESA_CONSUMER_KEY', ''));
        $this->consumerSecret = (string)($config['consumer_secret'] ?? self::env('MPESA_CONSUMER_SECRET', ''));
        $this->timeout = (int)($config['timeout'] ?? self::env('MPESA_HTTP_TIMEOUT', '30'));
        $this->verifySsl = filter_var($config['verify_ssl'] ?? self::env('MPESA_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN);

        if (! in_array($this->environment, ['sandbox', 'live'], true)) {
            throw new RuntimeException('MPESA_ENV must be sandbox or live.');
        }

        if ($this->consumerKey === '' || $this->consumerSecret === '') {
            throw new RuntimeException('MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET are required.');
        }
    }

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public static function env(string $name, ?string $default = null): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        return ($value === false || $value === null || $value === '') ? $default : (string)$value;
    }

    public function baseUrl(): string
    {
        return $this->environment === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    public function generateToken(): string
    {
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);
        $response = $this->request('GET', '/oauth/v1/generate?grant_type=client_credentials', null, ['Authorization: Basic ' . $credentials]);
        $data = json_decode($response, true);

        if (! is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Daraja token response did not contain access_token.');
        }

        return (string)$data['access_token'];
    }

    public static function generateLiveToken(): string
    {
        return (new self(['environment' => 'live']))->generateToken();
    }

    public static function generateSandBoxToken(): string
    {
        return (new self(['environment' => 'sandbox']))->generateToken();
    }

    public function post(string $path, array $payload): array
    {
        $raw = $this->request('POST', $path, $payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->generateToken(),
        ]);

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }

    public function stkPush(float $amount, string $phoneNumber, string $callbackUrl, string $accountReference, string $description, ?string $shortCode = null, ?string $passkey = null): array
    {
        $shortCode = $shortCode ?? self::env('MPESA_SHORTCODE', '');
        $passkey = $passkey ?? self::env('MPESA_PASSKEY', '');

        if ($shortCode === '' || $passkey === '') {
            throw new RuntimeException('MPESA_SHORTCODE and MPESA_PASSKEY are required for STK Push.');
        }

        $timestamp = date('YmdHis');
        $phone = self::normalizePhone($phoneNumber);

        return $this->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $shortCode,
            'Password' => base64_encode($shortCode . $passkey . $timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => self::env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
            'Amount' => self::formatAmount($amount),
            'PartyA' => $phone,
            'PartyB' => $shortCode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ]);
    }

    public function queryStkPush(string $checkoutRequestId, ?string $shortCode = null, ?string $passkey = null): array
    {
        $shortCode = $shortCode ?? self::env('MPESA_SHORTCODE', '');
        $passkey = $passkey ?? self::env('MPESA_PASSKEY', '');
        $timestamp = date('YmdHis');

        return $this->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $shortCode,
            'Password' => base64_encode($shortCode . $passkey . $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    public static function STKPushQuery($environment, $checkoutRequestID, $businessShortCode, $password, $timestamp): string
    {
        return json_encode((new self(['environment' => (string)$environment]))->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => (string)$businessShortCode,
            'Password' => (string)$password,
            'Timestamp' => (string)$timestamp,
            'CheckoutRequestID' => (string)$checkoutRequestID,
        ]));
    }

    public function STKPushSimulation($businessShortCode, $lipaNaMpesaPasskey, $transactionType, $amount, $partyA, $partyB, $phoneNumber, $callBackURL, $accountReference, $transactionDesc, $remark = null): string
    {
        return json_encode($this->stkPush((float)$amount, (string)$phoneNumber, (string)$callBackURL, (string)$accountReference, (string)$transactionDesc, (string)$businessShortCode, (string)$lipaNaMpesaPasskey));
    }

    public static function c2b($shortCode, $commandID, $amount, $msisdn, $billRefNumber): string
    {
        return json_encode((new self())->post('/mpesa/c2b/v1/simulate', [
            'ShortCode' => (string)$shortCode,
            'CommandID' => (string)$commandID,
            'Amount' => self::formatAmount((float)$amount),
            'Msisdn' => self::normalizePhone((string)$msisdn),
            'BillRefNumber' => (string)$billRefNumber,
        ]));
    }

    public function finishTransaction(bool $status = true): array
    {
        $result = $status
            ? ['ResultDesc' => 'Confirmation Service request accepted successfully', 'ResultCode' => '0']
            : ['ResultDesc' => 'Confirmation Service not accepted', 'ResultCode' => '1'];

        header('Content-Type: application/json');
        echo json_encode($result);
        return $result;
    }

    public function getDataFromCallback(): string
    {
        $data = file_get_contents('php://input');
        return is_string($data) ? $data : '';
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            $digits = '254' . $digits;
        }
        if (! preg_match('/^\d{10,15}$/', $digits)) {
            throw new RuntimeException('Invalid phone number. Use international format, for example 254712345678.');
        }
        return $digits;
    }

    private static function formatAmount(float $amount): int|float
    {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }
        return floor($amount) === $amount ? (int)$amount : $amount;
    }

    private function request(string $method, string $path, ?array $payload, array $headers): string
    {
        $curl = curl_init($this->baseUrl() . '/' . ltrim($path, '/'));
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ]);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Daraja request failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Daraja returned HTTP ' . $status . ': ' . $body);
        }
        return (string)$body;
    }
}
