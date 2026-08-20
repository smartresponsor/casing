<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use PHPUnit\Framework\TestCase;

final class CaseBoundaryTest extends TestCase
{
    public function testSubmittedCaseCopiesDraftEnvelopeWithoutPromotingFactsToVerifiedState(): void
    {
        $catalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $category = new CatalogCategoryEntity($catalog, 'Product', 'product', 'retailing.product', 0);
        $category->setPublished(true);
        $category->setWorkflowState('published');

        $draft = new CaseDraftEntity('actor-123', 'retailing.product');
        $draft->setCatalogCategory($category);
        $draft->setDescription('Package arrived damaged.');
        $draft->setSuppliedFacts(['claimedAmount' => '49.95']);
        $draft->setContributionData(['shipping' => ['trackingReference' => 'carrier-owned-value']]);
        $draft->setAttachmentReferences(['attachment-1']);

        $case = new CaseEntity($draft, $category);

        self::assertSame('actor-123', $case->getActorId());
        self::assertSame('retailing.product', $case->getBusinessContext());
        self::assertSame(['claimedAmount' => '49.95'], $case->getSuppliedFacts());
        self::assertSame(['shipping' => ['trackingReference' => 'carrier-owned-value']], $case->getContributionData());
        self::assertSame(['attachment-1'], $case->getAttachmentReferences());
        self::assertSame(CaseStatus::Submitted, $case->getStatus());
    }

    public function testCaseFollowUpIsAppendedWithoutReplacingSubmittedFacts(): void
    {
        $catalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $category = new CatalogCategoryEntity($catalog, 'Service', 'service', 'retailing.service', 0);
        $category->setPublished(true);
        $category->setWorkflowState('published');

        $draft = new CaseDraftEntity('actor-123', 'retailing.service');
        $draft->setCatalogCategory($category);
        $draft->setSuppliedFacts(['serviceDispute' => ['description' => 'Original claim.']]);
        $case = new CaseEntity($draft, $category);
        $case->transitionTo(CaseStatus::NeedsInformation);
        $case->appendFollowUp('Here is the requested detail.');
        $case->transitionTo(CaseStatus::Processing);

        self::assertSame('Original claim.', $case->getSuppliedFacts()['serviceDispute']['description']);
        self::assertSame([['message' => 'Here is the requested detail.']], $case->getSuppliedFacts()['followUp']);
        self::assertSame(CaseStatus::Processing, $case->getStatus());
    }

    public function testLifecycleRejectsInvalidStatusJump(): void
    {
        $catalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $category = new CatalogCategoryEntity($catalog, 'Product', 'product', 'retailing.product', 0);
        $category->setPublished(true);
        $category->setWorkflowState('published');
        $draft = new CaseDraftEntity('actor-123', 'retailing.product');
        $draft->setCatalogCategory($category);
        $case = new CaseEntity($draft, $category);

        self::assertTrue($case->canTransitionTo(CaseStatus::Processing));
        self::assertFalse($case->canTransitionTo(CaseStatus::Resolved));
        $this->expectException(\DomainException::class);
        $case->transitionToAllowed(CaseStatus::Resolved);
    }
}
