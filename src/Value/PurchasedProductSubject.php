<?php

declare(strict_types=1);

namespace App\Casing\Value;

final readonly class PurchasedProductSubject
{
    public function __construct(
        public string $orderReference,
        public string $orderNumber,
        public string $itemReference,
        public int $quantity,
        public string $currency,
        public string $unitPrice,
        public string $orderStatus,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'orderReference' => $this->orderReference,
            'orderNumber' => $this->orderNumber,
            'itemReference' => $this->itemReference,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'unitPrice' => $this->unitPrice,
            'orderStatus' => $this->orderStatus,
        ];
    }
}
