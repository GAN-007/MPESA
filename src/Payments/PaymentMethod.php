<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

enum PaymentMethod: string
{
    case MOBILE_MONEY = 'mobile_money';
    case BANK_TRANSFER = 'bank_transfer';
    case CARD = 'card';
    case QR = 'qr';
    case PAYMENT_LINK = 'payment_link';
    case BILL = 'bill';
    case WALLET = 'wallet';
    case CASH = 'cash';
}
