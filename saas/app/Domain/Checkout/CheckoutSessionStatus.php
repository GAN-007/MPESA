<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Checkout;

enum CheckoutSessionStatus: string
{
    case Open = 'open';
    case AwaitingCustomer = 'awaiting_customer';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
