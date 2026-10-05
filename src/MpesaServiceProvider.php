<?php
declare(strict_types=1);

namespace Safaricom\Mpesa;

use Illuminate\Support\ServiceProvider;
use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Payments\FileIdempotencyStore;
use Safaricom\Mpesa\Payments\IdempotencyStore;
use Safaricom\Mpesa\Payments\PaymentLinkService;
use Safaricom\Mpesa\Payments\PaymentOrchestrator;
use Safaricom\Mpesa\Payments\PaymentPlatform;
use Safaricom\Mpesa\Payments\PaymentRouter;
use Safaricom\Mpesa\Payments\ProviderRegistry;
use Safaricom\Mpesa\Payments\RiskEngine;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\Env;

final class MpesaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MpesaConfig::class, static fn () => MpesaConfig::fromEnvironment());
        $this->app->singleton(DarajaProvider::class, static fn ($app) => new DarajaProvider($app->make(MpesaConfig::class)));

        $this->app->singleton(Mpesa::class, static function ($app) {
            $instance = new Mpesa($app->make(MpesaConfig::class));
            Mpesa::useProvider($app->make(DarajaProvider::class));
            return $instance;
        });

        $this->app->singleton(ProviderRegistry::class, static function ($app) {
            $registry = new ProviderRegistry();
            $registry->register($app->make(DarajaProvider::class));
            return $registry;
        });

        $this->app->singleton(IdempotencyStore::class, static function ($app) {
            $directory = method_exists($app, 'storagePath')
                ? $app->storagePath('framework/cache/mpesa-idempotency')
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpesa-idempotency';
            return new FileIdempotencyStore($directory);
        });

        $this->app->singleton(PaymentOrchestrator::class, static function ($app) {
            $router = new PaymentRouter($app->make(ProviderRegistry::class));
            $router->prefer('mpesa', 100);
            return new PaymentOrchestrator($router, new RiskEngine(), $app->make(IdempotencyStore::class));
        });

        $this->app->singleton(PaymentPlatform::class, static function ($app) {
            $linkSecret = (string) Env::get('PAYMENTS_LINK_SECRET', '');
            $linkBaseUrl = (string) Env::get('PAYMENTS_LINK_BASE_URL', '');
            $linkService = ($linkSecret !== '' && $linkBaseUrl !== '')
                ? new PaymentLinkService($linkBaseUrl, $linkSecret)
                : null;
            $platform = new PaymentPlatform(
                [$app->make(DarajaProvider::class)],
                $app->make(IdempotencyStore::class),
                new RiskEngine(),
                $linkService,
            );
            $platform->preferProvider('mpesa', 100);
            return $platform;
        });
    }
}
