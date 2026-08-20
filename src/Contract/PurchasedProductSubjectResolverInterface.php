<?php

declare(strict_types=1);

namespace App\Casing\Contract;

use App\Casing\Value\PurchasedProductSubject;

interface PurchasedProductSubjectResolverInterface
{
    /** @return list<PurchasedProductSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject;
}
