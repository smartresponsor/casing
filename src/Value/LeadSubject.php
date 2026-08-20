<?php

declare(strict_types=1);

namespace App\Casing\Value;

final readonly class LeadSubject
{
    public function __construct(
        public string $leadReference,
        public string $status,
        public int $score,
    ) {
    }

    /** @return array{leadReference: string, status: string, score: int} */
    public function toArray(): array
    {
        return ['leadReference' => $this->leadReference, 'status' => $this->status, 'score' => $this->score];
    }
}
