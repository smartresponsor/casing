<?php

declare(strict_types=1);

namespace App\Casing\Integration\Relating;

use App\Casing\Contract\LeadSubjectResolverInterface;
use App\Casing\Value\LeadSubject;
use App\Entity\Lead;
use App\Service\VendorLeadReadServiceInterface;

final readonly class LeadSubjectResolver implements LeadSubjectResolverInterface
{
    public function __construct(private VendorLeadReadServiceInterface $leads)
    {
    }

    public function listForActor(string $actorId): array
    {
        $subjects = [];
        foreach ($this->leads->leadsForVendor(trim($actorId)) as $lead) {
            if ($lead instanceof Lead) {
                $subjects[] = new LeadSubject($lead->id(), $lead->status(), $lead->score());
            }
        }

        return $subjects;
    }

    public function resolve(string $actorId, string $leadReference): ?LeadSubject
    {
        $leadReference = trim($leadReference);
        if ('' === $leadReference) {
            return null;
        }

        foreach ($this->listForActor($actorId) as $subject) {
            if ($subject->leadReference === $leadReference) {
                return $subject;
            }
        }

        return null;
    }
}
