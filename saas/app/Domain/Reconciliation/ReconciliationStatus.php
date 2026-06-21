<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Reconciliation;

enum ReconciliationStatus: string
{
    case Matched = 'matched';
    case Duplicate = 'duplicate';
    case PendingProviderQuery = 'pending_provider_query';
    case MissingCallback = 'missing_callback';
    case AmountMismatch = 'amount_mismatch';
    case MerchantReferenceMismatch = 'merchant_reference_mismatch';
    case ManualReviewRequired = 'manual_review_required';
}
