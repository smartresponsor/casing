<?php

declare(strict_types=1);

namespace App\Casing\RepositoryInterface;

use App\Casing\Entity\CaseOutboxMessageEntity;

interface CaseOutboxMessageRepositoryInterface
{
    /**
     * Returns the oldest outbox messages that are eligible for dispatch now.
     *
     * @return list<CaseOutboxMessageEntity>
     */
    public function findDispatchable(int $limit, ?\DateTimeImmutable $now = null): array;

    /** @param array<string, mixed> $payload */
    public function store(string $caseReference, string $eventType, array $payload): void;

    public function flush(): void;
}
