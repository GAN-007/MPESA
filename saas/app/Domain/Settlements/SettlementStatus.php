<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Settlements;

enum SettlementStatus: string
{
    case Created = 'created';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
