<?php

declare(strict_types=1);

namespace App\Casing\Value;

final readonly class ServicePaymentSubject
{
    public function __construct(
        public string $paymentReference,
        public string $orderReference,
        public string $orderNumber,
        public string $status,
        public string $amount,
        public string $currency,
        public ?string $providerReference,
    ) {
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'paymentReference' => $this->paymentReference,
            'orderReference' => $this->orderReference,
            'orderNumber' => $this->orderNumber,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'providerReference' => $this->providerReference,
        ];
    }
}
