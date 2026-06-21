<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\EventDelivery;

final readonly class EventPayload
{
    public function __construct(
        public string $id,
        public EventType $type,
        public string $merchantId,
        public array $data,
        public string $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'merchant_id' => $this->merchantId,
            'data' => $this->data,
            'created_at' => $this->createdAt,
        ];
    }
}
