<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Event\CaseResolvedEvent;
use App\Casing\Repository\CaseRepository;
use App\Casing\Service\Outbox\CaseOutboxWriter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CaseLifecycleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CaseRepository $cases,
        private CaseOutboxWriter $outbox,
    ) {
    }

    public function transition(CaseEntity $case, CaseStatus $target): void
    {
        $case->transitionToAllowed($target);
        $this->cases->save($case, false);

        if (CaseStatus::Resolved === $target) {
            $event = new CaseResolvedEvent(
                $case->getCaseReference(),
                $case->getActorId(),
                $case->getBusinessContext(),
                $case->getCategoryPath(),
                (new \DateTimeImmutable())->format(DATE_ATOM),
            );
            $this->outbox->store($case->getCaseReference(), CaseResolvedEvent::class, get_object_vars($event));
        }

        $this->entityManager->flush();
    }
}
