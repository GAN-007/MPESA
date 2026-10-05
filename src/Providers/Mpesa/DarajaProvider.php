<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Providers\Mpesa;

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Http\HttpClient;
use Safaricom\Mpesa\Http\NativeHttpClient;
use Safaricom\Mpesa\Payments\PaymentMethod;
use Safaricom\Mpesa\Payments\PaymentProvider;
use Safaricom\Mpesa\Payments\PaymentRequest;
use Safaricom\Mpesa\Payments\PaymentResult;
use Safaricom\Mpesa\Payments\PaymentStatus;
use Safaricom\Mpesa\Support\PhoneNumber;

final class DarajaProvider implements PaymentProvider
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly MpesaConfig $config,
        ?HttpClient $http = null,
    ) {
        $this->http = $http ?? new NativeHttpClient($config->timeoutSeconds, $config->connectTimeoutSeconds);
    }

    private readonly HttpClient $http;

    public function name(): string
    {
        return 'mpesa';
    }

    public function supports(PaymentRequest $request): bool
    {
        return $request->amount->currency === 'KES'
            && in_array($request->method, [PaymentMethod::MOBILE_MONEY, PaymentMethod::BILL, PaymentMethod::PAYMENT_LINK, PaymentMethod::QR], true);
    }

    public function collect(PaymentRequest $request): PaymentResult
    {
        if (!$this->supports($request)) {
            throw PaymentException::provider('M-PESA does not support the requested rail/currency combination.');
        }

        if ($request->method === PaymentMethod::QR) {
            $shortCode = (string) ($request->metadata['shortcode'] ?? $this->config->shortCode ?? $request->payee);
            $response = $this->dynamicQr([
                'MerchantName' => (string) ($request->metadata['merchant_name'] ?? $request->payee),
                'RefNo' => $request->reference,
                'Amount' => $request->amount->darajaAmount(),
                'TrxCode' => (string) ($request->metadata['trx_code'] ?? 'PB'),
                'CPI' => $shortCode,
                'Size' => (string) ($request->metadata['qr_size'] ?? '300'),
            ]);
            return new PaymentResult(
                paymentId: $request->id,
                provider: $this->name(),
                status: PaymentStatus::REQUIRES_ACTION,
                providerReference: $response['RequestID'] ?? $response['ConversationID'] ?? null,
                customerMessage: $response['ResponseDescription'] ?? 'Scan the generated M-PESA QR code to complete payment.',
                raw: $response,
            );
        }

        $shortCode = (string) ($request->metadata['shortcode'] ?? $this->config->shortCode ?? $request->payee);
        $passkey = (string) ($request->metadata['passkey'] ?? $this->config->passkey ?? '');
        $callback = (string) ($request->metadata['callback_url'] ?? $this->config->callbackUrl ?? '');
        if ($shortCode === '' || $passkey === '' || $callback === '') {
            throw PaymentException::configuration('M-PESA collection requires shortcode, passkey and callback URL.');
        }

        $phone = PhoneNumber::normalizeKenyan($request->payer);
        $response = $this->stkPush([
            'BusinessShortCode' => $shortCode,
            'TransactionType' => (string) ($request->metadata['transaction_type'] ?? 'CustomerPayBillOnline'),
            'Amount' => $request->amount->darajaAmount(),
            'PartyA' => $phone,
            'PartyB' => (string) ($request->metadata['party_b'] ?? $shortCode),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback,
            'AccountReference' => $request->reference,
            'TransactionDesc' => $request->description,
            'Passkey' => $passkey,
        ]);

        $responseCode = (string) ($response['ResponseCode'] ?? '');
        $status = $responseCode === '0' ? PaymentStatus::PENDING : PaymentStatus::FAILED;
        return new PaymentResult(
            paymentId: $request->id,
            provider: $this->name(),
            status: $status,
            providerReference: $response['CheckoutRequestID'] ?? $response['MerchantRequestID'] ?? null,
            customerMessage: $response['CustomerMessage'] ?? $response['ResponseDescription'] ?? null,
            raw: $response,
        );
    }

    public function query(string $providerReference, array $context = []): PaymentResult
    {
        $shortCode = (string) ($context['shortcode'] ?? $this->config->shortCode ?? '');
        $passkey = (string) ($context['passkey'] ?? $this->config->passkey ?? '');
        if ($providerReference === '' || $shortCode === '' || $passkey === '') {
            throw PaymentException::validation('STK query requires checkout request id, shortcode and passkey.');
        }
        $response = $this->stkQuery($providerReference, $shortCode, $passkey);
        $resultCode = isset($response['ResultCode']) ? (string) $response['ResultCode'] : null;
        $status = match ($resultCode) {
            '0' => PaymentStatus::SUCCEEDED,
            null => PaymentStatus::PENDING,
            default => PaymentStatus::FAILED,
        };
        return new PaymentResult(
            paymentId: (string) ($context['payment_id'] ?? $providerReference),
            provider: $this->name(),
            status: $status,
            providerReference: $providerReference,
            customerMessage: $response['ResultDesc'] ?? $response['ResponseDescription'] ?? null,
            raw: $response,
        );
    }

    public function generateAccessToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh && $this->accessToken !== null && $this->accessTokenExpiresAt > time() + 30) {
            return $this->accessToken;
        }

        $credentials = base64_encode($this->config->consumerKey . ':' . $this->config->consumerSecret);
        $response = $this->http->request(
            'GET',
            $this->config->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials',
            ['Authorization' => 'Basic ' . $credentials, 'Accept' => 'application/json'],
        );
        $json = $response->json();
        if (!$response->successful() || empty($json['access_token'])) {
            throw PaymentException::provider($this->providerError($json, $response->body), $response->status);
        }

        $this->accessToken = (string) $json['access_token'];
        $expiresIn = max(60, (int) ($json['expires_in'] ?? 3599));
        $this->accessTokenExpiresAt = time() + $expiresIn - 60;
        return $this->accessToken;
    }

    public function stkPush(array $payload): array
    {
        $this->requireKeys($payload, ['BusinessShortCode', 'TransactionType', 'Amount', 'PartyA', 'PartyB', 'PhoneNumber', 'CallBackURL', 'AccountReference', 'TransactionDesc']);
        $passkey = (string) ($payload['Passkey'] ?? $this->config->passkey ?? '');
        if ($passkey === '') {
            throw PaymentException::configuration('M-PESA passkey is required for STK Push.');
        }
        unset($payload['Passkey']);
        $timestamp = gmdate('YmdHis');
        $payload['Password'] = base64_encode((string) $payload['BusinessShortCode'] . $passkey . $timestamp);
        $payload['Timestamp'] = $timestamp;
        return $this->post('/mpesa/stkpush/v1/processrequest', $payload);
    }

    public function stkQuery(string $checkoutRequestId, ?string $businessShortCode = null, ?string $passkey = null): array
    {
        $shortCode = $businessShortCode ?? $this->config->shortCode;
        $passkey ??= $this->config->passkey;
        if ($checkoutRequestId === '' || $shortCode === null || $passkey === null || $shortCode === '' || $passkey === '') {
            throw PaymentException::validation('CheckoutRequestID, BusinessShortCode and passkey are required.');
        }
        $timestamp = gmdate('YmdHis');
        return $this->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $shortCode,
            'Password' => base64_encode($shortCode . $passkey . $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    public function stkQueryWithPassword(string $checkoutRequestId, string $businessShortCode, string $password, string $timestamp): array
    {
        if ($checkoutRequestId === '' || $businessShortCode === '' || $password === '' || preg_match('/^\d{14}$/', $timestamp) !== 1) {
            throw PaymentException::validation('Legacy STK query requires checkout request id, shortcode, password and a 14-digit timestamp.');
        }
        return $this->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    public function registerC2bUrls(string $shortCode, string $confirmationUrl, string $validationUrl, string $responseType = 'Completed'): array
    {
        if (!in_array($responseType, ['Completed', 'Cancelled'], true)) {
            throw PaymentException::validation('C2B ResponseType must be Completed or Cancelled.');
        }
        foreach ([$confirmationUrl, $validationUrl] as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw PaymentException::validation('C2B callback URLs must be absolute URLs.');
            }
            if ($this->config->environment === 'live' && strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                throw PaymentException::validation('Production C2B callback URLs must use HTTPS.');
            }
        }
        return $this->post('/mpesa/c2b/v2/registerurl', [
            'ShortCode' => $shortCode,
            'ResponseType' => $responseType,
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl,
        ]);
    }

    public function simulateC2b(string $shortCode, string $commandId, int $amount, string $msisdn, string $billReference = ''): array
    {
        if ($this->config->environment !== 'sandbox') {
            throw PaymentException::validation('C2B simulation is only available in the Daraja sandbox.');
        }
        return $this->post('/mpesa/c2b/v1/simulate', [
            'ShortCode' => $shortCode,
            'CommandID' => $commandId,
            'Amount' => $amount,
            'Msisdn' => PhoneNumber::normalizeKenyan($msisdn),
            'BillRefNumber' => $billReference,
        ]);
    }

    public function b2c(array $payload): array
    {
        $payload['OriginatorConversationID'] ??= 'gan-' . bin2hex(random_bytes(6));
        if (isset($payload['Occasion']) && !isset($payload['Occassion'])) {
            $payload['Occassion'] = $payload['Occasion'];
            unset($payload['Occasion']);
        }
        $this->requireKeys($payload, ['OriginatorConversationID', 'InitiatorName', 'SecurityCredential', 'CommandID', 'Amount', 'PartyA', 'PartyB', 'Remarks', 'QueueTimeOutURL', 'ResultURL']);
        $payload['PartyB'] = PhoneNumber::normalizeKenyan((string) $payload['PartyB']);
        return $this->post('/mpesa/b2c/v3/paymentrequest', $payload);
    }

    public function b2b(array $payload): array
    {
        $this->requireKeys($payload, ['Initiator', 'SecurityCredential', 'CommandID', 'SenderIdentifierType', 'RecieverIdentifierType', 'Amount', 'PartyA', 'PartyB', 'AccountReference', 'Remarks', 'QueueTimeOutURL', 'ResultURL']);
        return $this->post('/mpesa/b2b/v1/paymentrequest', $payload);
    }

    public function businessBuyGoods(array $payload): array
    {
        $payload['CommandID'] = 'BusinessBuyGoods';
        return $this->b2b($payload);
    }

    public function businessPayBill(array $payload): array
    {
        $payload['CommandID'] = 'BusinessPayBill';
        return $this->b2b($payload);
    }

    public function taxRemittance(array $payload): array
    {
        $this->requireKeys($payload, ['Initiator', 'SecurityCredential', 'CommandID', 'SenderIdentifierType', 'RecieverIdentifierType', 'Amount', 'PartyA', 'PartyB', 'AccountReference', 'Remarks', 'QueueTimeOutURL', 'ResultURL']);
        return $this->post('/mpesa/b2b/v1/remittax', $payload);
    }

    public function transactionStatus(array $payload): array
    {
        $this->requireKeys($payload, ['Initiator', 'SecurityCredential', 'CommandID', 'PartyA', 'IdentifierType', 'ResultURL', 'QueueTimeOutURL', 'Remarks']);
        if (empty($payload['TransactionID']) && empty($payload['OriginalConversationID'])) {
            throw PaymentException::validation('Transaction status requires TransactionID or OriginalConversationID.');
        }
        return $this->post('/mpesa/transactionstatus/v1/query', $payload);
    }

    public function accountBalance(array $payload): array
    {
        $this->requireKeys($payload, ['CommandID', 'Initiator', 'SecurityCredential', 'PartyA', 'IdentifierType', 'Remarks', 'QueueTimeOutURL', 'ResultURL']);
        return $this->post('/mpesa/accountbalance/v1/query', $payload);
    }

    public function reversal(array $payload): array
    {
        $this->requireKeys($payload, ['CommandID', 'Initiator', 'SecurityCredential', 'TransactionID', 'Amount', 'ReceiverParty', 'RecieverIdentifierType', 'ResultURL', 'QueueTimeOutURL', 'Remarks']);
        return $this->post('/mpesa/reversal/v1/request', $payload);
    }

    public function dynamicQr(array $payload): array
    {
        $this->requireKeys($payload, ['MerchantName', 'RefNo', 'Amount', 'TrxCode', 'CPI', 'Size']);
        $allowed = ['BG', 'WA', 'PB', 'SM', 'SB'];
        if (!in_array((string) $payload['TrxCode'], $allowed, true)) {
            throw PaymentException::validation('Dynamic QR TrxCode must be one of BG, WA, PB, SM or SB.');
        }
        return $this->post('/mpesa/qrcode/v1/generate', $payload);
    }

    public function b2bExpressCheckout(array $payload): array
    {
        return $this->post('/v1/ussdpush/get-msisdn', $payload);
    }

    public function registerPullTransactions(array $payload): array
    {
        return $this->post('/pulltransactions/v1/register', $payload);
    }

    public function pullTransactions(array $payload): array
    {
        return $this->post('/pulltransactions/v1/query', $payload);
    }

    public function billManagerOptIn(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/optin', $payload);
    }

    public function billManagerSingleInvoice(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/single-invoicing', $payload);
    }

    public function billManagerBulkInvoice(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/bulk-invoicing', $payload);
    }

    public function billManagerReconciliation(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/reconciliation', $payload);
    }

    public function billManagerCancelSingleInvoice(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/cancel-single-invoice', $payload);
    }

    public function billManagerCancelBulkInvoice(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/cancel-bulk-invoice', $payload);
    }

    public function billManagerChangeOptInDetails(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/change-optin-details', $payload);
    }

    public function billManagerChangeInvoice(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/change-invoice', $payload);
    }

    public function billManagerChangeInvoices(array $payload): array
    {
        return $this->post('/v1/billmanager-invoice/change-invoices', $payload);
    }

    public static function encryptSecurityCredential(string $initiatorPassword, string $certificatePem): string
    {
        if ($initiatorPassword === '') {
            throw PaymentException::validation('Initiator password is required.');
        }
        $publicKey = openssl_pkey_get_public($certificatePem);
        if ($publicKey === false) {
            throw PaymentException::validation('Unable to read M-PESA X.509 public certificate.');
        }
        $encrypted = '';
        $ok = openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        if (!$ok) {
            throw PaymentException::validation('Unable to encrypt security credential with the supplied certificate.');
        }
        return base64_encode($encrypted);
    }

    private function post(string $path, array $payload): array
    {
        $attempt = 0;
        $refreshedFor401 = false;
        do {
            $token = $this->generateAccessToken($refreshedFor401);
            $response = $this->http->request(
                'POST',
                $this->config->baseUrl() . $path,
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Client-Request-Id' => bin2hex(random_bytes(12)),
                ],
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            $json = [];
            try {
                $json = $response->json();
            } catch (PaymentException $jsonError) {
                if ($response->successful()) {
                    throw $jsonError;
                }
            }

            if ($response->status === 401 && !$refreshedFor401) {
                $this->accessToken = null;
                $this->accessTokenExpiresAt = 0;
                $refreshedFor401 = true;
                continue;
            }

            if ($response->successful()) {
                return $json;
            }

            if (($response->status === 429 || $response->status >= 500) && $attempt < $this->config->retries) {
                usleep((int) (100000 * (2 ** $attempt)));
                $attempt++;
                continue;
            }

            throw PaymentException::provider($this->providerError($json, $response->body), $response->status);
        } while (true);
    }

    private function requireKeys(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
                throw PaymentException::validation("Missing required Daraja field: {$key}");
            }
        }
    }

    private function providerError(array $json, string $raw): string
    {
        foreach (['errorMessage', 'error_description', 'ResponseDescription', 'ResultDesc', 'message'] as $key) {
            if (isset($json[$key]) && is_scalar($json[$key])) {
                return (string) $json[$key];
            }
        }
        return $raw !== '' ? $raw : 'Unknown Daraja API error.';
    }
}
