<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Payments;

enum PaymentStatus: string
{
    case Created = 'created';
    case PendingCustomerAuthorization = 'pending_customer_authorization';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Reversed = 'reversed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Created => in_array($next, [self::PendingCustomerAuthorization, self::Cancelled, self::Expired], true),
            self::PendingCustomerAuthorization => in_array($next, [self::Processing, self::Paid, self::Failed, self::Cancelled, self::Expired], true),
            self::Processing => in_array($next, [self::Paid, self::Failed, self::Expired], true),
            self::Paid => in_array($next, [self::Reversed, self::Refunded, self::PartiallyRefunded], true),
            self::PartiallyRefunded => in_array($next, [self::Refunded], true),
            default => false,
        };
    }
}
