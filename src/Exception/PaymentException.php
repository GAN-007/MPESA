<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Exception;

use RuntimeException;

final class PaymentException extends RuntimeException
{
    public static function configuration(string $message): self { return new self('Configuration error: ' . $message); }
    public static function validation(string $message): self { return new self('Validation error: ' . $message); }
    public static function transport(string $message): self { return new self('Transport error: ' . $message); }
    public static function provider(string $message, int $code = 0): self { return new self('Provider error: ' . $message, $code); }
}
