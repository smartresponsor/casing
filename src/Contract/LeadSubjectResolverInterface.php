<?php

declare(strict_types=1);

namespace App\Casing\Contract;

use App\Casing\Value\LeadSubject;

interface LeadSubjectResolverInterface
{
    /** @return list<LeadSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $leadReference): ?LeadSubject;
}
