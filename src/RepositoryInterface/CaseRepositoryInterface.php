<?php

declare(strict_types=1);

namespace App\Casing\RepositoryInterface;

use App\Casing\Entity\CaseEntity;

interface CaseRepositoryInterface
{
    /** @return list<CaseEntity> */
    public function findActorCases(string $actorId): array;

    public function findActorCase(string $caseReference, string $actorId): ?CaseEntity;

    public function save(CaseEntity $case, bool $flush = true): void;

    public function flush(): void;

    /** @param callable(): mixed $operation */
    public function transactional(callable $operation): mixed;
}
