<?php
declare(strict_types=1);
namespace Safaricom\Mpesa\Support;
use Safaricom\Mpesa\Exception\PaymentException;
final class PhoneNumber { public static function normalizeKenyan(string|int $phone):string{$digits=preg_replace('/\D+/','',(string)$phone)??'';if(str_starts_with($digits,'0')&&strlen($digits)===10)$digits='254'.substr($digits,1);elseif(strlen($digits)===9&&(str_starts_with($digits,'7')||str_starts_with($digits,'1')))$digits='254'.$digits;if(!preg_match('/^254(?:7\d{8}|1\d{8})$/',$digits))throw PaymentException::validation('Invalid Kenyan MSISDN. Expected 2547XXXXXXXX or 2541XXXXXXXX.');return $digits;} }
