<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

enum PaymentStatus: string
{
    case CREATED = 'created';
    case REQUIRES_ACTION = 'requires_action';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REVERSED = 'reversed';
    case CANCELLED = 'cancelled';
}
