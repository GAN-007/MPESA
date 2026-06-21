<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Billing;

enum BillingPlan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Scale = 'scale';
    case Enterprise = 'enterprise';
}
