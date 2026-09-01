<?php

declare(strict_types=1);

namespace App\Casing\Event\Domain;

final readonly class CaseResolvedEvent
{
    public function __construct(
        public string $caseReference,
        public string $actorId,
        public string $businessContext,
        public string $categoryPath,
        public string $occurredAt,
    ) {
    }
}
