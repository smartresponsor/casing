<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\RepositoryInterface\CaseDraftRepositoryInterface;
use App\Casing\ResolverInterface\Paying\CaseServicePaymentSubjectResolverInterface;

final readonly class CaseServiceDisputeIntakeService
{
    public function __construct(
        private CaseServicePaymentSubjectResolverInterface $subjects,
        private CaseDraftRepositoryInterface $drafts,
    ) {
    }

    public function associatePayment(CaseDraftEntity $draft, string $paymentReference): void
    {
        $subject = $this->subjects->resolve($draft->getActorId(), $paymentReference);
        if (null === $subject) {
            throw new \DomainException('We could not associate this payment with your account.');
        }

        $references = array_values(array_filter(
            $draft->getSubjectReferences(),
            static fn (array $reference): bool => !in_array($reference['component'] ?? null, ['ordering', 'paying'], true),
        ));
        $references[] = ['component' => 'ordering', 'type' => 'order', 'id' => $subject->orderReference];
        $references[] = ['component' => 'paying', 'type' => 'payment', 'id' => $subject->paymentReference];
        $draft->setSubjectReferences($references);

        $contributions = $draft->getContributionData();
        $contributions['paying.service_dispute_subject'] = $subject->toArray();
        $draft->setContributionData($contributions);
        $draft->setCurrentStep('details');
        $this->persist($draft);
    }

    public function recordCustomerClaim(CaseDraftEntity $draft, string $typeCode, string $description): void
    {
        $typeCode = trim($typeCode);
        $description = trim($description);
        if ('' === $typeCode) {
            throw new \InvalidArgumentException('Dispute type is required.');
        }
        if ('' === $description) {
            throw new \InvalidArgumentException('Dispute description is required.');
        }

        $contributions = $draft->getContributionData();
        $contributions['cataloging.support_type'] = [
            'catalogCode' => 'retailing',
            'categoryPath' => 'retailing.service',
            'supportKind' => 'dispute',
            'typeCode' => $typeCode,
        ];
        $draft->setContributionData($contributions);

        $facts = $draft->getSuppliedFacts();
        $facts['serviceDispute'] = ['description' => $description];
        $draft->setSuppliedFacts($facts);
        $draft->setCurrentStep('review');
        $this->persist($draft);
    }

    private function persist(CaseDraftEntity $draft): void
    {
        $this->drafts->save($draft);
    }
}
