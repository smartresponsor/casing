<?php

declare(strict_types=1);

namespace App\Casing\Service\Outbox;

use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;

final readonly class CaseOutboxWriter
{
    public function __construct(private CaseOutboxMessageRepositoryInterface $messages)
    {
    }

    /** @param array<string, mixed> $payload */
    public function store(string $caseReference, string $eventType, array $payload): void
    {
        $this->messages->store($caseReference, $eventType, $payload);
    }
}
