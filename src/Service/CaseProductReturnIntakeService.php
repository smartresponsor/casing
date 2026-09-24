<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\RepositoryInterface\CaseDraftRepositoryInterface;
use App\Casing\ResolverInterface\Ordering\CasePurchasedProductSubjectResolverInterface;

final readonly class CaseProductReturnIntakeService
{
    public function __construct(
        private CasePurchasedProductSubjectResolverInterface $subjects,
        private CaseDraftRepositoryInterface $drafts,
    ) {
    }

    public function associatePurchasedProduct(CaseDraftEntity $draft, string $orderReference, string $itemReference): void
    {
        $subject = $this->subjects->resolve($draft->getActorId(), $orderReference, $itemReference);
        if (null === $subject) {
            throw new \DomainException('We could not associate this purchased product with your account.');
        }

        $references = array_values(array_filter(
            $draft->getSubjectReferences(),
            static fn (array $reference): bool => 'ordering' !== ($reference['component'] ?? null),
        ));
        $references[] = ['component' => 'ordering', 'type' => 'order', 'id' => $subject->orderReference];
        $references[] = ['component' => 'ordering', 'type' => 'order-item', 'id' => $subject->itemReference];
        $draft->setSubjectReferences($references);

        $contributions = $draft->getContributionData();
        $contributions['ordering.return_subject'] = $subject->toArray();
        $draft->setContributionData($contributions);
        $draft->setCurrentStep('details');
        $this->drafts->save($draft);
    }

    public function recordCustomerClaim(CaseDraftEntity $draft, string $typeCode, string $reason, ?int $quantity = null): void
    {
        $typeCode = trim($typeCode);
        $reason = trim($reason);
        if ('' === $typeCode) {
            throw new \InvalidArgumentException('Return type is required.');
        }
        if ('' === $reason) {
            throw new \InvalidArgumentException('Return reason is required.');
        }
        if (null !== $quantity && $quantity < 1) {
            throw new \InvalidArgumentException('Return quantity must be greater than zero.');
        }

        $contributions = $draft->getContributionData();
        $contributions['cataloging.support_type'] = [
            'catalogCode' => 'retailing',
            'categoryPath' => 'retailing.product',
            'supportKind' => 'return',
            'typeCode' => $typeCode,
        ];
        $draft->setContributionData($contributions);

        $facts = $draft->getSuppliedFacts();
        $facts['return'] = ['reason' => $reason, 'quantity' => $quantity];
        $draft->setSuppliedFacts($facts);
        $draft->setCurrentStep('review');
        $this->drafts->save($draft);
    }
}
