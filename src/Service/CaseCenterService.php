<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Repository\CaseRepository;

final readonly class CaseCenterService
{
    public function __construct(private CaseRepository $cases)
    {
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
        if (CaseStatus::NeedsInformation !== $case->getStatus() || !$case->canTransitionTo(CaseStatus::Processing)) {
            throw new \DomainException('This case is not waiting for additional information.');
        }

        $message = trim($message);
        if ('' === $message) {
            throw new \InvalidArgumentException('Additional information is required.');
        }

        $case->appendFollowUp($message);
        $case->transitionToAllowed(CaseStatus::Processing);
        $this->cases->save($case);

        return $case;
    }
}
