<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseEntity;
use App\Casing\Entity\CaseInformationRequestEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Repository\CaseInformationRequestRepository;
use App\Casing\Repository\CaseRepository;

final readonly class CaseInformationRequestService
{
    public function __construct(
        private CaseInformationRequestRepository $requests,
        private CaseRepository $cases,
    ) {
    }

    public function request(CaseEntity $case, string $question): CaseInformationRequestEntity
    {
        if (!$case->canTransitionTo(CaseStatus::NeedsInformation)) {
            throw new \DomainException('Case cannot request additional information from its current status.');
        }
        if ($this->requests->findOpenForCase($case) instanceof CaseInformationRequestEntity) {
            throw new \DomainException('Case already has an open information request.');
        }

        $request = new CaseInformationRequestEntity($case, $question);
        $this->requests->save($request, false);
        $case->transitionToAllowed(CaseStatus::NeedsInformation);
        $this->cases->save($case);

        return $request;
    }

    public function openForCase(CaseEntity $case): ?CaseInformationRequestEntity
    {
        return $this->requests->findOpenForCase($case);
    }

    public function answer(CaseEntity $case, string $answer): CaseInformationRequestEntity
    {
        if (CaseStatus::NeedsInformation !== $case->getStatus() || !$case->canTransitionTo(CaseStatus::Processing)) {
            throw new \DomainException('This case is not waiting for additional information.');
        }

        $request = $this->requests->findOpenForCase($case);
        if (!$request instanceof CaseInformationRequestEntity) {
            throw new \DomainException('This case does not have an open information request.');
        }

        $request->answer($answer);
        $this->requests->save($request, false);
        $case->appendFollowUp($answer);
        $case->transitionToAllowed(CaseStatus::Processing);
        $this->cases->save($case);

        return $request;
    }
}
