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
}
