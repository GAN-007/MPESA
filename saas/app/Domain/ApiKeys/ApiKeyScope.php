<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\ApiKeys;

enum ApiKeyScope: string
{
    case PaymentsRead = 'payments:read';
    case PaymentsWrite = 'payments:write';
    case PaymentLinksRead = 'payment_links:read';
    case PaymentLinksWrite = 'payment_links:write';
    case RefundsWrite = 'refunds:write';
    case SettlementsRead = 'settlements:read';
    case WebhooksWrite = 'webhooks:write';
    case Admin = 'admin';
}
