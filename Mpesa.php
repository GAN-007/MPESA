<?php

declare(strict_types=1);

/*
 * Backward-compatible entrypoint for projects that previously included
 * this top-level file directly. New Composer-based projects should use
 * vendor/autoload.php and the Safaricom\Mpesa\Mpesa class from src/.
 */

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/Exceptions/MpesaException.php';
    require_once __DIR__ . '/src/Exceptions/MpesaHttpException.php';
    require_once __DIR__ . '/src/Config/MpesaConfig.php';
    require_once __DIR__ . '/src/Support/MpesaResponse.php';
    require_once __DIR__ . '/src/Support/PhoneNumber.php';
    require_once __DIR__ . '/src/Http/CurlMpesaClient.php';
    require_once __DIR__ . '/src/Mpesa.php';
}
