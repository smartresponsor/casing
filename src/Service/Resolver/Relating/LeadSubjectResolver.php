<?php

declare(strict_types=1);

namespace App\Casing\Service\Resolver\Relating;

use App\Casing\ServiceInterface\Resolver\LeadSubjectResolverInterface;
use App\Casing\Value\LeadSubject;
use App\Relating\Entity\RelationLead;
use App\Relating\Service\RelationVendorLeadReadServiceInterface;

final readonly class LeadSubjectResolver implements LeadSubjectResolverInterface
{
    public function __construct(private RelationVendorLeadReadServiceInterface $leads)
    {
    }

    public function listForActor(string $actorId): array
    {
        $subjects = [];
        foreach ($this->leads->leadsForVendor(trim($actorId)) as $lead) {
            if ($lead instanceof RelationLead) {
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
