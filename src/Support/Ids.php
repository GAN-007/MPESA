<?php
declare(strict_types=1);
namespace Safaricom\Mpesa\Support;
final class Ids { public static function payment(string $prefix='pay'):string{return $prefix.'_'.bin2hex(random_bytes(12));} public static function idempotency(string $namespace,array $material):string{return hash('sha256',$namespace.'|'.json_encode($material,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));} public static function base64UrlEncode(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');} public static function base64UrlDecode(string $value):string{$padding=(4-strlen($value)%4)%4;$decoded=base64_decode(strtr($value.str_repeat('=',$padding),'-_','+/'),true);if($decoded===false)throw new \InvalidArgumentException('Invalid base64url value.');return $decoded;} }
