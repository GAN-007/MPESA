<?php
declare(strict_types=1);

namespace Safaricom\Mpesa;

use Safaricom\Mpesa\Exception\PaymentException;

final class TransactionCallbacks
{
    public static function processB2BRequestCallback(?string $json = null): string { return self::encode(self::normalizeResult(self::decode($json))); }
    public static function processB2CRequestCallback(?string $json = null): string { return self::encode(self::normalizeResult(self::decode($json))); }
    public static function processC2BRequestValidation(?string $json = null): string { return self::encode(self::normalizeC2B(self::decode($json))); }
    public static function processC2BRequestConfirmation(?string $json = null): string { return self::encode(self::normalizeC2B(self::decode($json))); }
    public static function processAccountBalanceRequestCallback(?string $json = null): string { return self::encode(self::normalizeResult(self::decode($json))); }
    public static function processReversalRequestCallBack(?string $json = null): string { return self::encode(self::normalizeResult(self::decode($json))); }

    public static function processSTKPushRequestCallback(?string $json = null): string
    {
        $payload = self::decode($json);
        $callback = $payload['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) throw PaymentException::validation('Invalid STK callback payload.');
        $metadata = [];
        foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
            if (is_array($item) && isset($item['Name'])) $metadata[(string) $item['Name']] = $item['Value'] ?? null;
        }
        return self::encode([
            'resultCode' => $callback['ResultCode'] ?? null,
            'resultDesc' => $callback['ResultDesc'] ?? null,
            'merchantRequestID' => $callback['MerchantRequestID'] ?? null,
            'checkoutRequestID' => $callback['CheckoutRequestID'] ?? null,
            'amount' => $metadata['Amount'] ?? null,
            'mpesaReceiptNumber' => $metadata['MpesaReceiptNumber'] ?? null,
            'balance' => $metadata['Balance'] ?? null,
            'transactionDate' => $metadata['TransactionDate'] ?? null,
            'phoneNumber' => $metadata['PhoneNumber'] ?? null,
            'callbackMetadata' => $metadata,
        ]);
    }

    public static function processSTKPushQueryRequestCallback(?string $json = null): string
    {
        $payload = self::decode($json);
        return self::encode([
            'responseCode' => $payload['ResponseCode'] ?? null,
            'responseDescription' => $payload['ResponseDescription'] ?? null,
            'merchantRequestID' => $payload['MerchantRequestID'] ?? null,
            'checkoutRequestID' => $payload['CheckoutRequestID'] ?? null,
            'resultCode' => $payload['ResultCode'] ?? null,
            'resultDesc' => $payload['ResultDesc'] ?? null,
        ]);
    }

    public static function processTransactionStatusRequestCallback(?string $json = null): string
    {
        return self::encode(self::normalizeResult(self::decode($json)));
    }

    public static function decode(?string $json = null): array
    {
        $json ??= file_get_contents('php://input') ?: '';
        if ($json === '') throw PaymentException::validation('Callback body is empty.');
        try { $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw PaymentException::validation('Callback contains invalid JSON: ' . $e->getMessage()); }
        if (!is_array($decoded)) throw PaymentException::validation('Callback JSON must decode to an object.');
        return $decoded;
    }

    private static function normalizeC2B(array $payload): array
    {
        $map = [
            'TransactionType' => 'transactionType', 'TransID' => 'transID', 'TransTime' => 'transTime',
            'TransAmount' => 'transAmount', 'BusinessShortCode' => 'businessShortCode',
            'BillRefNumber' => 'billRefNumber', 'InvoiceNumber' => 'invoiceNumber',
            'OrgAccountBalance' => 'orgAccountBalance', 'ThirdPartyTransID' => 'thirdPartyTransID',
            'MSISDN' => 'MSISDN', 'FirstName' => 'firstName', 'MiddleName' => 'middleName', 'LastName' => 'lastName',
        ];
        $result = [];
        foreach ($map as $source => $target) $result[$target] = $payload[$source] ?? null;
        return $result;
    }

    private static function normalizeResult(array $payload): array
    {
        $result = $payload['Result'] ?? null;
        if (!is_array($result)) throw PaymentException::validation('Callback is missing the Result object.');
        $parameters = [];
        $items = $result['ResultParameters']['ResultParameter'] ?? [];
        if (isset($items['Key'])) $items = [$items];
        if (is_array($items)) {
            foreach ($items as $item) if (is_array($item) && isset($item['Key'])) $parameters[(string) $item['Key']] = $item['Value'] ?? null;
        }
        $referenceData = [];
        $refs = $result['ReferenceData']['ReferenceItem'] ?? [];
        if (isset($refs['Key'])) $refs = [$refs];
        if (is_array($refs)) {
            foreach ($refs as $item) if (is_array($item) && isset($item['Key'])) $referenceData[(string) $item['Key']] = $item['Value'] ?? null;
        }
        $normalized = [
            'resultType' => $result['ResultType'] ?? null,
            'resultCode' => $result['ResultCode'] ?? null,
            'resultDesc' => $result['ResultDesc'] ?? null,
            'originatorConversationID' => $result['OriginatorConversationID'] ?? null,
            'conversationID' => $result['ConversationID'] ?? null,
            'transactionID' => $result['TransactionID'] ?? null,
            'resultParameters' => $parameters,
            'referenceData' => $referenceData,
        ];
        foreach ($parameters as $key => $value) {
            $normalized[$key] = $value;
            $normalized[lcfirst($key)] ??= $value;
        }
        return $normalized;
    }

    private static function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
