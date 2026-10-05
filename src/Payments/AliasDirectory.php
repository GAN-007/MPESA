<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class AliasDirectory
{
    /** @var array<string, PaymentAlias> */
    private array $aliases = [];

    public function register(PaymentAlias $alias): void
    {
        $key = $this->key($alias->type, $alias->alias);
        if (isset($this->aliases[$key]) && $this->aliases[$key]->destination !== $alias->destination) {
            throw PaymentException::validation('Alias already belongs to a different destination.');
        }
        $this->aliases[$key] = $alias;
    }

    public function resolve(string $type, string $alias): PaymentAlias
    {
        $key = $this->key($type, $alias);
        return $this->aliases[$key] ?? throw PaymentException::validation('Unknown payment alias.');
    }

    public function unregister(string $type, string $alias): void
    {
        unset($this->aliases[$this->key($type, $alias)]);
    }

    private function key(string $type, string $alias): string
    {
        $type = strtolower(trim($type));
        $alias = strtolower(trim($alias));
        if ($type === '' || $alias === '') {
            throw PaymentException::validation('Alias type and value are required.');
        }
        return $type . ':' . $alias;
    }
}
