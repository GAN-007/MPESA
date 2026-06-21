<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Audit;

enum AuditAction: string
{
    case MerchantCreated = 'merchant.created';
    case MerchantUpdated = 'merchant.updated';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';
    case PaymentCreated = 'payment.created';
    case PaymentUpdated = 'payment.updated';
    case SettlementApproved = 'settlement.approved';
    case CredentialUpdated = 'credential.updated';
}
