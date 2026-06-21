<?php

declare(strict_types=1);

/* Backward-compatible entrypoint. Prefer Composer PSR-4 autoloading. */

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/MpesaServiceProvider.php';
}
