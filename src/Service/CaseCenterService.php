<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseEntity;
use App\Casing\RepositoryInterface\CaseRepositoryInterface;

final readonly class CaseCenterService
{
    public function __construct(
        private CaseRepositoryInterface $cases,
        private CaseInformationRequestService $informationRequests,
    ) {
    }

    /** @return list<CaseEntity> */
    public function listForActor(string $actorId): array
    {
        return $this->cases->findActorCases($actorId);
    }

    public function requireActorCase(string $caseReference, string $actorId): CaseEntity
    {
        $case = $this->cases->findActorCase(trim($caseReference), trim($actorId));
        if (!$case instanceof CaseEntity) {
            throw new \DomainException('We could not associate this case with your account.');
        }

        return $case;
    }

    public function provideInformation(string $caseReference, string $actorId, string $message): CaseEntity
    {
        $case = $this->requireActorCase($caseReference, $actorId);
        $this->informationRequests->answer($case, $message);

        return $case;
    }

    public function openInformationRequest(CaseEntity $case): ?\App\Casing\Entity\CaseInformationRequestEntity
    {
        return $this->informationRequests->openForCase($case);
    }
}
