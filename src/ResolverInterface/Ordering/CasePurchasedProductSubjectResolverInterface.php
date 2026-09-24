<?php

declare(strict_types=1);

namespace App\Casing\ResolverInterface\Ordering;

use App\Casing\Value\CasePurchasedProductSubject;

interface CasePurchasedProductSubjectResolverInterface
{
    /** @return list<CasePurchasedProductSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $orderReference, string $itemReference): ?CasePurchasedProductSubject;

    /** @return list<CasePurchasedProductSubject> */
    public function listForActorOrder(string $actorId, string $orderReference): array;
}
