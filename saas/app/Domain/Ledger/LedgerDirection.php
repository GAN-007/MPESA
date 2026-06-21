<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Ledger;

enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
