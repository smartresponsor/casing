<?php

declare(strict_types=1);

namespace App\Casing\Resolver\Relating;

use App\Casing\ResolverInterface\Relating\CaseLeadSubjectResolverInterface;
use App\Casing\Value\CaseLeadSubject;
use App\Relating\Entity\RelationLeadEntity;
use App\Relating\Service\RelationVendorLeadReadServiceInterface;

final readonly class CaseLeadSubjectResolver implements CaseLeadSubjectResolverInterface
{
    public function __construct(private RelationVendorLeadReadServiceInterface $leads)
    {
    }

    public function listForActor(string $actorId): array
    {
        $subjects = [];
        foreach ($this->leads->leadsForVendor(trim($actorId)) as $lead) {
            if ($lead instanceof RelationLeadEntity) {
                $subjects[] = new CaseLeadSubject($lead->id(), $lead->status(), $lead->score());
            }
        }

        return $subjects;
    }

    public function resolve(string $actorId, string $leadReference): ?CaseLeadSubject
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
