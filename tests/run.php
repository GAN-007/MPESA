<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Safaricom\\Mpesa\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require_once $file;
});
require_once __DIR__ . '/FakeHttpClient.php';

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Payments\AliasDirectory;
use Safaricom\Mpesa\Payments\Agent;
use Safaricom\Mpesa\Payments\AgentNetwork;
use Safaricom\Mpesa\Payments\FileIdempotencyStore;
use Safaricom\Mpesa\Payments\ReconciliationItem;
use Safaricom\Mpesa\Payments\ReconciliationService;
use Safaricom\Mpesa\Payments\CardAuthorisation;
use Safaricom\Mpesa\Payments\InMemoryIdempotencyStore;
use Safaricom\Mpesa\Payments\Ledger;
use Safaricom\Mpesa\Payments\PaymentAlias;
use Safaricom\Mpesa\Payments\PaymentLinkService;
use Safaricom\Mpesa\Payments\PaymentMethod;
use Safaricom\Mpesa\Payments\PaymentOrchestrator;
use Safaricom\Mpesa\Payments\PaymentPlatform;
use Safaricom\Mpesa\Payments\PaymentProvider;
use Safaricom\Mpesa\Payments\PaymentRequest;
use Safaricom\Mpesa\Payments\PaymentResult;
use Safaricom\Mpesa\Payments\PaymentRouter;
use Safaricom\Mpesa\Payments\PaymentStatus;
use Safaricom\Mpesa\Payments\ProviderRegistry;
use Safaricom\Mpesa\Payments\RewardsEngine;
use Safaricom\Mpesa\Payments\Biller;
use Safaricom\Mpesa\Payments\RiskEngine;
use Safaricom\Mpesa\Payments\SettlementWallet;
use Safaricom\Mpesa\Payments\VirtualCardControls;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\CallbackSecurity;
use Safaricom\Mpesa\Support\Money;
use Safaricom\Mpesa\Support\PhoneNumber;
use Safaricom\Mpesa\TransactionCallbacks;
use Tests\FakeHttpClient;

$tests = [];
function test(string $name, callable $fn): void { global $tests; $tests[] = [$name, $fn]; }
function same(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) throw new RuntimeException($message !== '' ? $message : 'Expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
}
function truth(bool $condition, string $message = 'Expected condition to be true'): void { if (!$condition) throw new RuntimeException($message); }
function throws(callable $fn, string $class): void {
    try { $fn(); } catch (Throwable $e) { if ($e instanceof $class) return; throw new RuntimeException('Expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage()); }
    throw new RuntimeException('Expected exception ' . $class . ' but none was thrown.');
}

test('Money uses integer minor units and deterministic decimal conversion', function (): void {
    $money = Money::fromDecimal('123.45', 'KES');
    same(12345, $money->minor);
    same('123.45', $money->decimal());
    same(123, Money::fromDecimal('123.40')->darajaAmount());
});

test('Kenyan phone numbers normalize to Daraja MSISDN', function (): void {
    same('254712345678', PhoneNumber::normalizeKenyan('0712 345 678'));
    same('254112345678', PhoneNumber::normalizeKenyan('+254 112 345 678'));
    throws(fn () => PhoneNumber::normalizeKenyan('12345'), PaymentException::class);
});

test('Signed payment links detect tampering and expiry', function (): void {
    $service = new PaymentLinkService('https://pay.example.test', str_repeat('s', 40));
    $link = $service->create('merchant-1', Money::fromDecimal('50.00'), 'INV-22', 600, ['order' => 22], 1000);
    $verified = $service->verify($link->token, 1200);
    same('merchant-1', $verified->merchant);
    same(5000, $verified->amount->minor);
    same(22, $verified->metadata['order']);
    throws(fn () => $service->verify($link->token . 'x', 1200), PaymentException::class);
    throws(fn () => $service->verify($link->token, 2000), PaymentException::class);
});

test('Alias directory resolves InstaPay-style aliases without exposing rail details', function (): void {
    $directory = new AliasDirectory();
    $directory->register(new PaymentAlias('@gan', 'username', 'wallet:gan', 'KES'));
    same('wallet:gan', $directory->resolve('username', '@GAN')->destination);
});

test('Double-entry ledger posts balanced transfers idempotently', function (): void {
    $ledger = new Ledger();
    $amount = Money::fromDecimal('100.00');
    $tx = $ledger->transfer('wallet:alice', 'merchant:shop', $amount, 'INV-1', 'jnl-fixed');
    same(-10000, $ledger->balance('wallet:alice'));
    same(10000, $ledger->balance('merchant:shop'));
    $ledger->post($tx);
    same(10000, $ledger->balance('merchant:shop'));
});

test('Settlement wallet separates pending and spendable balances', function (): void {
    $wallet = new SettlementWallet('merchant:1');
    $wallet->creditSettled(Money::fromDecimal('20.00'));
    $wallet->creditPending('T1', Money::fromDecimal('30.00'), time() + 1);
    same(2000, $wallet->position()->spendable()->minor);
    same(5000, $wallet->position()->total()->minor);
    $wallet->settleDue(time() + 2);
    same(5000, $wallet->position()->spendable()->minor);
});

test('Virtual card controls enforce channel and daily spending policies', function (): void {
    $controls = new VirtualCardControls('tok_123', 10000, onlineEnabled: true, contactlessEnabled: false, internationalEnabled: false);
    $approved = $controls->authorise(3000, 'merchant-a', true, false, false, '2026-10-06');
    truth($approved instanceof CardAuthorisation && $approved->approved);
    same('contactless_disabled', $controls->authorise(1000, 'merchant-a', false, true, false, '2026-10-06')->reason);
    same('international_disabled', $controls->authorise(1000, 'merchant-a', true, false, true, '2026-10-06')->reason);
    same('daily_limit_exceeded', $controls->authorise(8000, 'merchant-a', true, false, false, '2026-10-06')->reason);
});

test('Rewards engine earns and redeems deterministic points', function (): void {
    $rewards = new RewardsEngine();
    same(100, $rewards->earn('buyer', 10000, 100));
    $rewards->redeem('buyer', 40);
    same(60, $rewards->balance('buyer'));
});

test('Orchestrator routes and idempotently returns one provider result', function (): void {
    $provider = new class implements PaymentProvider {
        public int $calls = 0;
        public function name(): string { return 'fake'; }
        public function supports(PaymentRequest $request): bool { return $request->amount->currency === 'KES'; }
        public function collect(PaymentRequest $request): PaymentResult { $this->calls++; return new PaymentResult($request->id, 'fake', PaymentStatus::PENDING, 'provider-1'); }
        public function query(string $providerReference, array $context = []): PaymentResult { return new PaymentResult('x', 'fake', PaymentStatus::SUCCEEDED, $providerReference); }
    };
    $registry = new ProviderRegistry();
    $registry->register($provider);
    $router = new PaymentRouter($registry);
    $orchestrator = new PaymentOrchestrator($router, new RiskEngine(), new InMemoryIdempotencyStore());
    $request = new PaymentRequest(Money::fromDecimal('10'), PaymentMethod::MOBILE_MONEY, '254712345678', '174379', 'INV', 'Payment');
    $one = $orchestrator->collect($request, 'idem-1');
    $two = $orchestrator->collect($request, 'idem-1');
    same('provider-1', $one->providerReference);
    same('provider-1', $two->providerReference);
    same(1, $provider->calls);
});

test('Durable file idempotency survives store re-instantiation', function (): void {
    $directory = sys_get_temp_dir() . '/mpesa-idem-' . bin2hex(random_bytes(6));
    $store = new FileIdempotencyStore($directory);
    $calls = 0;
    $fingerprint = hash('sha256', 'request-a');
    $first = $store->remember('checkout-1', $fingerprint, function () use (&$calls): PaymentResult {
        $calls++;
        return new PaymentResult('pay-1', 'fake', PaymentStatus::PENDING, 'remote-1', 'queued', ['x' => 1]);
    });
    $secondStore = new FileIdempotencyStore($directory);
    $second = $secondStore->remember('checkout-1', $fingerprint, function () use (&$calls): PaymentResult {
        $calls++;
        return new PaymentResult('pay-2', 'fake', PaymentStatus::FAILED);
    });
    same(1, $calls);
    same('remote-1', $first->providerReference);
    same('remote-1', $second->providerReference);
    throws(fn () => $secondStore->assertSameRequest('checkout-1', hash('sha256', 'different')), PaymentException::class);
    foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
    @rmdir($directory);
});

test('Agent network models cash-in and cash-out liquidity without creating money', function (): void {
    $network = new AgentNetwork();
    $agent = new Agent('agent-1', 'CBD Agent', 'KES', 100000, 80000);
    $network->register($agent);
    $network->cashIn('agent-1', '254712345678', Money::fromDecimal('200.00'), 'agt-in-1');
    same(80000, $agent->electronicFloatMinor);
    same(100000, $agent->cashMinor);
    $network->cashOut('agent-1', '254712345678', Money::fromDecimal('150.00'), 'agt-out-1');
    same(95000, $agent->electronicFloatMinor);
    same(85000, $agent->cashMinor);
    $network->cashOut('agent-1', '254712345678', Money::fromDecimal('150.00'), 'agt-out-1');
    same(95000, $agent->electronicFloatMinor);
    same(85000, $agent->cashMinor);
});

test('Reconciliation detects missing and mismatched provider transactions', function (): void {
    $service = new ReconciliationService();
    $report = $service->reconcile(
        [
            new ReconciliationItem('A', 1000, 'KES', 'succeeded'),
            new ReconciliationItem('B', 2000, 'KES', 'pending'),
            new ReconciliationItem('C', 3000, 'KES', 'succeeded'),
        ],
        [
            new ReconciliationItem('A', 1000, 'KES', 'succeeded'),
            new ReconciliationItem('B', 2500, 'KES', 'succeeded'),
            new ReconciliationItem('D', 4000, 'KES', 'succeeded'),
        ],
    );
    same(['A'], $report->matched);
    same(['C'], $report->missingAtProvider);
    same(['D'], $report->missingInternally);
    truth(isset($report->mismatches['B']['amountMinor'], $report->mismatches['B']['status']));
    truth(!$report->clean());
});

test('PaymentPlatform intertwines alias, signed link, biller, ledger and rewards journeys', function (): void {
    $provider = new class implements PaymentProvider {
        public int $calls = 0;
        public function name(): string { return 'platform-fake'; }
        public function supports(PaymentRequest $request): bool { return $request->amount->currency === 'KES'; }
        public function collect(PaymentRequest $request): PaymentResult {
            $this->calls++;
            return new PaymentResult($request->id, $this->name(), PaymentStatus::SUCCEEDED, 'PX-' . $this->calls, 'paid', ['reference' => $request->reference]);
        }
        public function query(string $providerReference, array $context = []): PaymentResult { return new PaymentResult('x', $this->name(), PaymentStatus::SUCCEEDED, $providerReference); }
    };
    $links = new PaymentLinkService('https://pay.example.test', str_repeat('k', 40));
    $platform = new PaymentPlatform([$provider], new InMemoryIdempotencyStore(), new RiskEngine(), $links);
    $platform->aliases->register(new PaymentAlias('@merchant', 'username', 'merchant:123', 'KES'));
    $aliasResult = $platform->payAlias('username', '@merchant', '254712345678', Money::fromDecimal('10.00'), 'ALIAS-1', 'Alias payment', 'idem-alias');
    same(PaymentStatus::SUCCEEDED, $aliasResult->status);

    $platform->billers->register(new Biller('power', 'Power Utility', 'merchant:power', 'KES', '/^[A-Z0-9]{4,20}$/'));
    $billResult = $platform->payBill('power', '254712345678', 'ACC123', Money::fromDecimal('20.00'), 'idem-bill');
    same(PaymentStatus::SUCCEEDED, $billResult->status);

    $link = $platform->createPaymentLink('merchant:123', Money::fromDecimal('30.00'), 'LINK-1', 600, [], 1000);
    $linkResult = $platform->collectPaymentLink($link->token, '254712345678', 'idem-link', [], 1100);
    same(PaymentStatus::SUCCEEDED, $linkResult->status);

    $platform->recordSuccessfulPayment('jnl-platform', 'clearing:mpesa', 'merchant:123', Money::fromDecimal('30.00'), 'LINK-1', 'customer-1', 100);
    same(3000, $platform->ledger->balance('merchant:123'));
    same(30, $platform->rewards->balance('customer-1'));
    same(3, $provider->calls);
});

test('Daraja B2C v3 adds originator conversation id and maps legacy Occasion spelling', function (): void {
    $http = new FakeHttpClient();
    $http->queueJson(200, ['access_token' => 'TOKEN', 'expires_in' => 3599]);
    $http->queueJson(200, ['ConversationID' => 'AG_1', 'OriginatorConversationID' => 'gan-abc', 'ResponseCode' => '0', 'ResponseDescription' => 'Accepted']);
    $provider = new DarajaProvider(new MpesaConfig('sandbox', 'key', 'secret'), $http);
    $provider->b2c([
        'InitiatorName' => 'testapi',
        'SecurityCredential' => 'encrypted',
        'CommandID' => 'BusinessPayment',
        'Amount' => 10,
        'PartyA' => '600000',
        'PartyB' => '0712345678',
        'Remarks' => 'Payout',
        'QueueTimeOutURL' => 'https://example.test/timeout',
        'ResultURL' => 'https://example.test/result',
        'Occasion' => 'Refund',
    ]);
    $payload = json_decode((string) $http->requests[1]['body'], true, 512, JSON_THROW_ON_ERROR);
    truth((bool) preg_match('/^gan-[a-f0-9]{12}$/', $payload['OriginatorConversationID']));
    same('Refund', $payload['Occassion']);
    truth(!array_key_exists('Occasion', $payload));
    same('254712345678', $payload['PartyB']);
});

test('Result callbacks retain structured parameters and backwards-friendly flattened aliases', function (): void {
    $json = json_encode([
        'Result' => [
            'ResultType' => 0,
            'ResultCode' => 0,
            'ResultDesc' => 'OK',
            'OriginatorConversationID' => 'O1',
            'ConversationID' => 'C1',
            'TransactionID' => 'T1',
            'ResultParameters' => ['ResultParameter' => [
                ['Key' => 'TransactionReceipt', 'Value' => 'ABC123'],
                ['Key' => 'TransactionAmount', 'Value' => 10],
            ]],
        ],
    ], JSON_THROW_ON_ERROR);
    $parsed = json_decode(TransactionCallbacks::processB2CRequestCallback($json), true, 512, JSON_THROW_ON_ERROR);
    same('ABC123', $parsed['resultParameters']['TransactionReceipt']);
    same('ABC123', $parsed['TransactionReceipt']);
    same('ABC123', $parsed['transactionReceipt']);
    same(10, $parsed['transactionAmount']);
});

test('Daraja provider caches OAuth token and builds secure STK request', function (): void {
    $http = new FakeHttpClient();
    $http->queueJson(200, ['access_token' => 'TOKEN', 'expires_in' => 3599]);
    $http->queueJson(200, [
        'MerchantRequestID' => 'MR1', 'CheckoutRequestID' => 'CR1', 'ResponseCode' => '0',
        'ResponseDescription' => 'Success. Request accepted for processing', 'CustomerMessage' => 'Success'
    ]);
    $config = new MpesaConfig('sandbox', 'key', 'secret', '174379', 'passkey', callbackUrl: 'https://merchant.test/callback');
    $provider = new DarajaProvider($config, $http);
    $result = $provider->collect(new PaymentRequest(Money::fromDecimal('1.00'), PaymentMethod::MOBILE_MONEY, '0712345678', '174379', 'INV1', 'Checkout'));
    same(PaymentStatus::PENDING, $result->status);
    same(2, count($http->requests));
    same('GET', $http->requests[0]['method']);
    truth(str_contains($http->requests[0]['headers']['Authorization'], 'Basic '));
    $payload = json_decode((string) $http->requests[1]['body'], true, 512, JSON_THROW_ON_ERROR);
    same('254712345678', $payload['PhoneNumber']);
    same('174379', $payload['BusinessShortCode']);
    truth(isset($payload['Password']) && $payload['Password'] !== 'passkey');
    truth((bool) preg_match('/^\d{14}$/', $payload['Timestamp']));
});

test('Daraja retries once on expired token and refreshes credentials', function (): void {
    $http = new FakeHttpClient();
    $http->queueJson(200, ['access_token' => 'OLD', 'expires_in' => 3599]);
    $http->queueJson(401, ['errorMessage' => 'Invalid Access Token']);
    $http->queueJson(200, ['access_token' => 'NEW', 'expires_in' => 3599]);
    $http->queueJson(200, ['ResponseCode' => '00', 'ResponseDescription' => 'OK', 'QRCode' => 'base64png']);
    $provider = new DarajaProvider(new MpesaConfig('sandbox', 'k', 's'), $http);
    $response = $provider->dynamicQr(['MerchantName'=>'Shop','RefNo'=>'R1','Amount'=>1,'TrxCode'=>'BG','CPI'=>'123456','Size'=>'300']);
    same('base64png', $response['QRCode']);
    same(4, count($http->requests));
});

test('Daraja callback source allow-list rejects untrusted IPv4 addresses', function (): void {
    truth(CallbackSecurity::isAllowedDarajaIp('196.201.214.200'));
    truth(!CallbackSecurity::isAllowedDarajaIp('203.0.113.7'));
    throws(fn () => CallbackSecurity::assertAllowedDarajaIp('203.0.113.7'), PaymentException::class);
    truth(CallbackSecurity::isAllowedDarajaIp('203.0.113.7', ['203.0.113.7']));
});

test('STK callback parsing is key-based instead of fragile index-based parsing', function (): void {
    $json = json_encode([
        'Body' => ['stkCallback' => [
            'MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'C1', 'ResultCode' => 0, 'ResultDesc' => 'OK',
            'CallbackMetadata' => ['Item' => [
                ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                ['Name' => 'Amount', 'Value' => 50],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                ['Name' => 'TransactionDate', 'Value' => 20261006010000],
            ]]
        ]]
    ], JSON_THROW_ON_ERROR);
    $parsed = json_decode(TransactionCallbacks::processSTKPushRequestCallback($json), true, 512, JSON_THROW_ON_ERROR);
    same('ABC123', $parsed['mpesaReceiptNumber']);
    same(50, $parsed['amount']);
    same(254712345678, $parsed['phoneNumber']);
});

test('C2B callback parser emits stable field names instead of callback values as object keys', function (): void {
    $json = json_encode(['TransactionType'=>'Pay Bill','TransID'=>'XYZ','TransTime'=>'20261006010000','TransAmount'=>'10','BusinessShortCode'=>'600000','BillRefNumber'=>'ACC1','MSISDN'=>'254712345678','FirstName'=>'A'], JSON_THROW_ON_ERROR);
    $parsed = json_decode(TransactionCallbacks::processC2BRequestConfirmation($json), true, 512, JSON_THROW_ON_ERROR);
    same('XYZ', $parsed['transID']);
    same('ACC1', $parsed['billRefNumber']);
});

$passed = 0;
$failed = 0;
foreach ($tests as [$name, $fn]) {
    try {
        $fn();
        echo "PASS  {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "FAIL  {$name}\n      " . $e::class . ": " . $e->getMessage() . "\n";
        $failed++;
    }
}
echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
