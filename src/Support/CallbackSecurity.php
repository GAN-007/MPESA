<?php
declare(strict_types=1);
namespace Safaricom\Mpesa\Support;
use Safaricom\Mpesa\Exception\PaymentException;
final class CallbackSecurity { public const DARAJA_GATEWAY_IPS=['196.201.214.200','196.201.214.206','196.201.213.114','196.201.214.207','196.201.214.208','196.201.213.44','196.201.212.127','196.201.212.138','196.201.212.129','196.201.212.136','196.201.212.74','196.201.212.69']; public static function isAllowedDarajaIp(string $ip,array $additionalAllowedIps=[]):bool{if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false)return false;return in_array($ip,array_merge(self::DARAJA_GATEWAY_IPS,$additionalAllowedIps),true);} public static function assertAllowedDarajaIp(string $ip,array $additionalAllowedIps=[]):void{if(!self::isAllowedDarajaIp($ip,$additionalAllowedIps))throw PaymentException::validation('Callback source IP is not in the configured Daraja allow-list.');} }
