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
        $catalog = new CatalogCatalogEntity('products', 'Products', 'product-commerce');
        $category = new CatalogCategoryEntity($catalog, 'Return', 'return', 'products.return', 1);
        $category->setPublished(true);
        $category->setWorkflowState('published');

        $draft = new CaseDraftEntity('actor-123', 'products');
        $draft->setCatalogCategory($category);
        $draft->setDescription('Package arrived damaged.');
        $draft->setSuppliedFacts(['claimedAmount' => '49.95']);
        $draft->setContributionData(['shipping' => ['trackingReference' => 'carrier-owned-value']]);
        $draft->setAttachmentReferences(['attachment-1']);

        $case = new CaseEntity($draft, $category);

        self::assertSame('actor-123', $case->getActorId());
        self::assertSame('products', $case->getBusinessContext());
        self::assertSame(['claimedAmount' => '49.95'], $case->getSuppliedFacts());
        self::assertSame(['shipping' => ['trackingReference' => 'carrier-owned-value']], $case->getContributionData());
        self::assertSame(['attachment-1'], $case->getAttachmentReferences());
        self::assertSame(CaseStatus::Submitted, $case->getStatus());
    }
}
