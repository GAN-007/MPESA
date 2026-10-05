<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class VirtualCardControls
{
    private bool $enabled = true;
    private int $spentTodayMinor = 0;
    private string $spendDate;
    /** @var array<string, bool> */
    private array $merchantAllowList = [];
    /** @var array<string, bool> */
    private array $merchantBlockList = [];

    public function __construct(
        public readonly string $tokenReference,
        private int $dailyLimitMinor,
        private bool $onlineEnabled = true,
        private bool $contactlessEnabled = true,
        private bool $internationalEnabled = false,
    ) {
        if ($tokenReference === '' || $dailyLimitMinor < 0) throw PaymentException::validation('Card token reference and non-negative daily limit are required.');
        $this->spendDate = gmdate('Y-m-d');
    }

    public function freeze(): void { $this->enabled = false; }
    public function unfreeze(): void { $this->enabled = true; }
    public function setDailyLimit(int $minor): void { if ($minor < 0) throw PaymentException::validation('Daily limit cannot be negative.'); $this->dailyLimitMinor = $minor; }
    public function setOnlineEnabled(bool $enabled): void { $this->onlineEnabled = $enabled; }
    public function setContactlessEnabled(bool $enabled): void { $this->contactlessEnabled = $enabled; }
    public function setInternationalEnabled(bool $enabled): void { $this->internationalEnabled = $enabled; }

    public function allowMerchant(string $merchant): void
    {
        $merchant = strtolower(trim($merchant));
        if ($merchant === '') throw PaymentException::validation('Merchant is required.');
        $this->merchantAllowList[$merchant] = true;
    }

    public function blockMerchant(string $merchant): void
    {
        $merchant = strtolower(trim($merchant));
        if ($merchant === '') throw PaymentException::validation('Merchant is required.');
        $this->merchantBlockList[$merchant] = true;
    }

    public function authorise(int $amountMinor, string $merchant, bool $online, bool $contactless, bool $international, ?string $date = null): CardAuthorisation
    {
        if ($amountMinor <= 0) return new CardAuthorisation(false, 'invalid_amount');
        $date ??= gmdate('Y-m-d');
        if ($date !== $this->spendDate) { $this->spentTodayMinor = 0; $this->spendDate = $date; }
        if (!$this->enabled) return new CardAuthorisation(false, 'card_frozen');
        if ($online && !$this->onlineEnabled) return new CardAuthorisation(false, 'online_disabled');
        if ($contactless && !$this->contactlessEnabled) return new CardAuthorisation(false, 'contactless_disabled');
        if ($international && !$this->internationalEnabled) return new CardAuthorisation(false, 'international_disabled');

        $merchantKey = strtolower(trim($merchant));
        if (isset($this->merchantBlockList[$merchantKey])) return new CardAuthorisation(false, 'merchant_blocked');
        if ($this->merchantAllowList !== [] && !isset($this->merchantAllowList[$merchantKey])) return new CardAuthorisation(false, 'merchant_not_allowed');
        if ($this->spentTodayMinor + $amountMinor > $this->dailyLimitMinor) return new CardAuthorisation(false, 'daily_limit_exceeded');

        $this->spentTodayMinor += $amountMinor;
        return new CardAuthorisation(true, 'approved');
    }
}
