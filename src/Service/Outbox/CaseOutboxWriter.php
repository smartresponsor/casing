<?php

declare(strict_types=1);

namespace App\Casing\Service\Outbox;

use App\Casing\Entity\CaseOutboxMessageEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CaseOutboxWriter
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param array<string, mixed> $payload */
    public function store(string $caseReference, string $eventType, array $payload): void
    {
        $this->entityManager->persist(new CaseOutboxMessageEntity($caseReference, $eventType, $payload));
    }
}
