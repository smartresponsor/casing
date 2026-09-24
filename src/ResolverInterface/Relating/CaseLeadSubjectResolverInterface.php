<?php

declare(strict_types=1);

namespace App\Casing\ResolverInterface\Relating;

use App\Casing\Value\CaseLeadSubject;

interface CaseLeadSubjectResolverInterface
{
    /** @return list<CaseLeadSubject> */
    public function listForActor(string $actorId): array;

    public function resolve(string $actorId, string $leadReference): ?CaseLeadSubject;
}
