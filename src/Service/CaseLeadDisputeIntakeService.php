<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\RepositoryInterface\CaseDraftRepositoryInterface;
use App\Casing\ResolverInterface\Relating\CaseLeadSubjectResolverInterface;

final readonly class CaseLeadDisputeIntakeService
{
    public function __construct(
        private CaseLeadSubjectResolverInterface $subjects,
        private CaseDraftRepositoryInterface $drafts,
    ) {
    }

    public function associateLead(CaseDraftEntity $draft, string $leadReference): void
    {
        $subject = $this->subjects->resolve($draft->getActorId(), $leadReference);
        if (null === $subject) {
            throw new \DomainException('We could not associate this lead with your account.');
        }

        $references = array_values(array_filter(
            $draft->getSubjectReferences(),
            static fn (array $reference): bool => 'relating' !== ($reference['component'] ?? null),
        ));
        $references[] = ['component' => 'relating', 'type' => 'lead', 'id' => $subject->leadReference];
        $draft->setSubjectReferences($references);

        $contributions = $draft->getContributionData();
        $contributions['relating.lead_dispute_subject'] = $subject->toArray();
        $draft->setContributionData($contributions);
        $draft->setCurrentStep('details');
        $this->persist($draft);
    }

    public function recordCustomerClaim(CaseDraftEntity $draft, string $typeCode, string $description): void
    {
        $typeCode = trim($typeCode);
        $description = trim($description);
        if ('' === $typeCode) {
            throw new \InvalidArgumentException('Lead dispute type is required.');
        }
        if ('' === $description) {
            throw new \InvalidArgumentException('Lead dispute description is required.');
        }

        $contributions = $draft->getContributionData();
        $contributions['cataloging.support_type'] = [
            'catalogCode' => 'leads',
            'categoryPath' => 'leads.dispute',
            'typeCode' => $typeCode,
        ];
        $draft->setContributionData($contributions);

        $facts = $draft->getSuppliedFacts();
        $facts['leadDispute'] = ['description' => $description];
        $draft->setSuppliedFacts($facts);
        $draft->setCurrentStep('review');
        $this->persist($draft);
    }

    private function persist(CaseDraftEntity $draft): void
    {
        $this->drafts->save($draft);
    }
}
