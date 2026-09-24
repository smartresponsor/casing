<?php

declare(strict_types=1);

namespace App\Casing\ResolverInterface\Paying;

use App\Casing\Value\CaseServicePaymentSubject;

interface CaseServicePaymentSubjectResolverInterface
{
    /** @return list<CaseServicePaymentSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $paymentReference): ?CaseServicePaymentSubject;
}
