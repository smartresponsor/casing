<?php

declare(strict_types=1);

namespace App\Casing\Contract;

use App\Casing\Value\PurchasedProductSubject;

interface PurchasedProductSubjectResolverInterface
{
    public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject;
}
