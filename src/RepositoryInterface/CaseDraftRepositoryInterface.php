<?php

declare(strict_types=1);

namespace App\Casing\RepositoryInterface;

use App\Casing\Entity\CaseDraftEntity;

interface CaseDraftRepositoryInterface
{
    public function findActorDraft(string $draftReference, string $actorId): ?CaseDraftEntity;

    public function save(CaseDraftEntity $draft, bool $flush = true): void;

    public function remove(CaseDraftEntity $draft, bool $flush = true): void;
}
