<?php

declare(strict_types=1);

namespace App\Casing\RepositoryInterface;

use App\Casing\Entity\CaseEntity;
use App\Casing\Entity\CaseInformationRequestEntity;

interface CaseInformationRequestRepositoryInterface
{
    public function findOpenForCase(CaseEntity $case): ?CaseInformationRequestEntity;

    public function save(CaseInformationRequestEntity $request, bool $flush = true): void;
}
