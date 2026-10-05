<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class CardAuthorisation
{
    public function __construct(public readonly bool $approved, public readonly string $reason) {}
}
