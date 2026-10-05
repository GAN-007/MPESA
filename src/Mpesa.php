<?php
declare(strict_types=1);

namespace Safaricom\Mpesa;

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Http\HttpClient;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\Env;

final class Mpesa
{
    private static ?DarajaProvider $provider = null;

    public function __construct(?MpesaConfig $config = null, ?HttpClient $http = null)
    {
        if ($config !== null) {
            self::$provider = new DarajaProvider($config, $http);
        }
    }

    public static function env(string $envParam): ?string
    {
        foreach ([getcwd() . '/.env', dirname(__DIR__) . '/.env'] as $path) {
            Env::load($path);
        }
        return Env::get($envParam);
    }

    public static function daraja(): DarajaProvider
    {
        return self::$provider ??= new DarajaProvider(MpesaConfig::fromEnvironment());
    }

    public static function useProvider(DarajaProvider $provider): void
    {
        self::$provider = $provider;
    }

    public static function generateLiveToken(): string
    {
        return self::providerFor('live')->generateAccessToken();
    }

    public static function generateSandBoxToken(): string
    {
        return self::providerFor('sandbox')->generateAccessToken();
    }

    public static function reversal($CommandID, $Initiator, $SecurityCredential, $TransactionID, $Amount, $ReceiverParty, $RecieverIdentifierType, $ResultURL, $QueueTimeOutURL, $Remarks, $Occasion = ''): object
    {
        $result = self::daraja()->reversal([
            'CommandID' => (string) $CommandID,
            'Initiator' => (string) $Initiator,
            'SecurityCredential' => (string) $SecurityCredential,
            'TransactionID' => (string) $TransactionID,
            'Amount' => (int) $Amount,
            'ReceiverParty' => (string) $ReceiverParty,
            'RecieverIdentifierType' => (string) $RecieverIdentifierType,
            'ResultURL' => (string) $ResultURL,
            'QueueTimeOutURL' => (string) $QueueTimeOutURL,
            'Remarks' => (string) $Remarks,
            'Occasion' => (string) $Occasion,
        ]);
        return json_decode(json_encode($result, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    public static function b2c($InitiatorName, $SecurityCredential, $CommandID, $Amount, $PartyA, $PartyB, $Remarks, $QueueTimeOutURL, $ResultURL, $Occasion = ''): string
    {
        return self::json(self::daraja()->b2c([
            'InitiatorName' => (string) $InitiatorName,
            'SecurityCredential' => (string) $SecurityCredential,
            'CommandID' => (string) $CommandID,
            'Amount' => (int) $Amount,
            'PartyA' => (string) $PartyA,
            'PartyB' => (string) $PartyB,
            'Remarks' => (string) $Remarks,
            'QueueTimeOutURL' => (string) $QueueTimeOutURL,
            'ResultURL' => (string) $ResultURL,
            'Occasion' => (string) $Occasion,
        ]));
    }

    public static function c2b($ShortCode, $CommandID, $Amount, $Msisdn, $BillRefNumber = ''): string
    {
        return self::json(self::daraja()->simulateC2b(
            (string) $ShortCode,
            (string) $CommandID,
            (int) $Amount,
            (string) $Msisdn,
            (string) $BillRefNumber,
        ));
    }

    public static function registerC2BUrls($ShortCode, $ConfirmationURL, $ValidationURL, $ResponseType = 'Completed'): string
    {
        return self::json(self::daraja()->registerC2bUrls(
            (string) $ShortCode,
            (string) $ConfirmationURL,
            (string) $ValidationURL,
            (string) $ResponseType,
        ));
    }

    public static function accountBalance($CommandID, $Initiator, $SecurityCredential, $PartyA, $IdentifierType, $Remarks, $QueueTimeOutURL, $ResultURL): string
    {
        return self::json(self::daraja()->accountBalance([
            'CommandID' => (string) $CommandID,
            'Initiator' => (string) $Initiator,
            'SecurityCredential' => (string) $SecurityCredential,
            'PartyA' => (string) $PartyA,
            'IdentifierType' => (string) $IdentifierType,
            'Remarks' => (string) $Remarks,
            'QueueTimeOutURL' => (string) $QueueTimeOutURL,
            'ResultURL' => (string) $ResultURL,
        ]));
    }

    public static function transactionStatus($Initiator, $SecurityCredential, $CommandID, $TransactionID, $PartyA, $IdentifierType, $ResultURL, $QueueTimeOutURL, $Remarks, $Occasion = ''): string
    {
        return self::json(self::daraja()->transactionStatus([
            'Initiator' => (string) $Initiator,
            'SecurityCredential' => (string) $SecurityCredential,
            'CommandID' => (string) $CommandID,
            'TransactionID' => (string) $TransactionID,
            'PartyA' => (string) $PartyA,
            'IdentifierType' => (string) $IdentifierType,
            'ResultURL' => (string) $ResultURL,
            'QueueTimeOutURL' => (string) $QueueTimeOutURL,
            'Remarks' => (string) $Remarks,
            'Occasion' => (string) $Occasion,
        ]));
    }

    public static function b2b($Initiator, $SecurityCredential, $Amount, $PartyA, $PartyB, $Remarks, $QueueTimeOutURL, $ResultURL, $AccountReference, $commandID, $SenderIdentifierType, $RecieverIdentifierType): string
    {
        return self::json(self::daraja()->b2b([
            'Initiator' => (string) $Initiator,
            'SecurityCredential' => (string) $SecurityCredential,
            'CommandID' => (string) $commandID,
            'SenderIdentifierType' => (string) $SenderIdentifierType,
            'RecieverIdentifierType' => (string) $RecieverIdentifierType,
            'Amount' => (int) $Amount,
            'PartyA' => (string) $PartyA,
            'PartyB' => (string) $PartyB,
            'AccountReference' => (string) $AccountReference,
            'Remarks' => (string) $Remarks,
            'QueueTimeOutURL' => (string) $QueueTimeOutURL,
            'ResultURL' => (string) $ResultURL,
        ]));
    }

    public static function STKPushSimulation($BusinessShortCode, $LipaNaMpesaPasskey, $TransactionType, $Amount, $PartyA, $PartyB, $PhoneNumber, $CallBackURL, $AccountReference, $TransactionDesc, $Remark = ''): string
    {
        return self::json(self::daraja()->stkPush([
            'BusinessShortCode' => (string) $BusinessShortCode,
            'Passkey' => (string) $LipaNaMpesaPasskey,
            'TransactionType' => (string) $TransactionType,
            'Amount' => (int) $Amount,
            'PartyA' => (string) $PartyA,
            'PartyB' => (string) $PartyB,
            'PhoneNumber' => (string) $PhoneNumber,
            'CallBackURL' => (string) $CallBackURL,
            'AccountReference' => (string) $AccountReference,
            'TransactionDesc' => (string) ($TransactionDesc !== '' ? $TransactionDesc : $Remark),
        ]));
    }

    public static function STKPushQuery(...$arguments): string
    {
        if (count($arguments) === 5) {
            [, $checkoutRequestID, $businessShortCode, $passwordOrPasskey, $timestamp] = $arguments;
            return self::json(self::legacyStkQuery((string) $checkoutRequestID, (string) $businessShortCode, (string) $passwordOrPasskey, (string) $timestamp));
        }
        if (count($arguments) === 4) {
            [$checkoutRequestID, $businessShortCode, $passwordOrPasskey, $timestamp] = $arguments;
            return self::json(self::legacyStkQuery((string) $checkoutRequestID, (string) $businessShortCode, (string) $passwordOrPasskey, (string) $timestamp));
        }
        if (count($arguments) === 1) {
            return self::json(self::daraja()->stkQuery((string) $arguments[0]));
        }
        throw new \InvalidArgumentException('STKPushQuery expects CheckoutRequestID alone, or the legacy 4/5 argument form.');
    }

    public static function dynamicQr(string $merchantName, string $reference, int $amount, string $transactionCode, string $creditPartyIdentifier, int $size = 300): string
    {
        return self::json(self::daraja()->dynamicQr([
            'MerchantName' => $merchantName,
            'RefNo' => $reference,
            'Amount' => $amount,
            'TrxCode' => $transactionCode,
            'CPI' => $creditPartyIdentifier,
            'Size' => (string) $size,
        ]));
    }

    public function finishTransaction(bool $status = true): string
    {
        $result = $status
            ? ['ResultDesc' => 'Confirmation Service request accepted successfully', 'ResultCode' => '0']
            : ['ResultDesc' => 'Confirmation Service not accepted', 'ResultCode' => '1'];
        $json = self::json($result);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo $json;
        return $json;
    }

    public function getDataFromCallback(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    private static function providerFor(string $environment): DarajaProvider
    {
        $base = MpesaConfig::fromEnvironment();
        $config = new MpesaConfig(
            environment: $environment,
            consumerKey: $base->consumerKey,
            consumerSecret: $base->consumerSecret,
            shortCode: $base->shortCode,
            passkey: $base->passkey,
            initiator: $base->initiator,
            securityCredential: $base->securityCredential,
            callbackUrl: $base->callbackUrl,
            timeoutSeconds: $base->timeoutSeconds,
            connectTimeoutSeconds: $base->connectTimeoutSeconds,
            retries: $base->retries,
        );
        return new DarajaProvider($config);
    }

    private static function legacyStkQuery(string $checkoutRequestId, string $businessShortCode, string $passwordOrPasskey, string $timestamp): array
    {
        if ($timestamp !== '' && preg_match('/^\d{14}$/', $timestamp) === 1) {
            return self::daraja()->stkQueryWithPassword($checkoutRequestId, $businessShortCode, $passwordOrPasskey, $timestamp);
        }
        return self::daraja()->stkQuery($checkoutRequestId, $businessShortCode, $passwordOrPasskey);
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
