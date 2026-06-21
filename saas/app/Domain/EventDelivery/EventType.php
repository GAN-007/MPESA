<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\EventDelivery;

enum EventType: string
{
    case PaymentCreated = 'payment.created';
    case PaymentPending = 'payment.pending';
    case PaymentPaid = 'payment.paid';
    case PaymentFailed = 'payment.failed';
    case PaymentExpired = 'payment.expired';
    case PaymentReversed = 'payment.reversed';
    case PaymentRefunded = 'payment.refunded';
    case SettlementCreated = 'settlement.created';
    case SettlementPaid = 'settlement.paid';
}
