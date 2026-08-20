<?php

declare(strict_types=1);

namespace App\Casing\Contract;

use App\Casing\Value\ServicePaymentSubject;

interface ServicePaymentSubjectResolverInterface
{
    /** @return list<ServicePaymentSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $paymentReference): ?ServicePaymentSubject;
}
