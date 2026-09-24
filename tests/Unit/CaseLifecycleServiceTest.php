<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Event\CaseResolvedEvent;
use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;
use App\Casing\RepositoryInterface\CaseRepositoryInterface;
use App\Casing\Service\CaseLifecycleService;
use App\Casing\Service\Outbox\CaseOutboxWriter;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use PHPUnit\Framework\TestCase;

final class CaseLifecycleServiceTest extends TestCase
{
    public function testProcessingTransitionPersistsAndFlushesWithoutOutboxEvent(): void
    {
        $case = $this->case();
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $messages = $this->createMock(CaseOutboxMessageRepositoryInterface::class);

        $cases->expects(self::once())->method('save')->with($case, false);
        $cases->expects(self::once())->method('flush');
        $messages->expects(self::never())->method('store');

        $service = new CaseLifecycleService($cases, new CaseOutboxWriter($messages));
        $service->transition($case, CaseStatus::Processing);

        self::assertSame(CaseStatus::Processing, $case->getStatus());
    }

    public function testResolvedTransitionStoresResolvedEventBeforeFlush(): void
    {
        $case = $this->case();
        $case->transitionToAllowed(CaseStatus::Processing);

        $cases = $this->createMock(CaseRepositoryInterface::class);
        $messages = $this->createMock(CaseOutboxMessageRepositoryInterface::class);

        $cases->expects(self::once())->method('save')->with($case, false);
        $cases->expects(self::once())->method('flush');
        $messages->expects(self::once())
            ->method('store')
            ->with(
                $case->getCaseReference(),
                CaseResolvedEvent::class,
                self::callback(static function (array $payload) use ($case): bool {
                    return $case->getCaseReference() === ($payload['caseReference'] ?? null)
                        && 'actor-123' === ($payload['actorId'] ?? null)
                        && 'retailing.product' === ($payload['businessContext'] ?? null)
                        && 'retailing.product' === ($payload['categoryPath'] ?? null)
                        && is_string($payload['occurredAt'] ?? null);
                }),
            );

        $service = new CaseLifecycleService($cases, new CaseOutboxWriter($messages));
        $service->transition($case, CaseStatus::Resolved);

        self::assertSame(CaseStatus::Resolved, $case->getStatus());
    }

    public function testInvalidTransitionDoesNotPersistAnything(): void
    {
        $case = $this->case();
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $messages = $this->createMock(CaseOutboxMessageRepositoryInterface::class);

        $cases->expects(self::never())->method('save');
        $cases->expects(self::never())->method('flush');
        $messages->expects(self::never())->method('store');

        $service = new CaseLifecycleService($cases, new CaseOutboxWriter($messages));

        $this->expectException(\DomainException::class);
        $service->transition($case, CaseStatus::Resolved);
    }

    private function case(): CaseEntity
    {
        $catalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $category = new CatalogCategoryEntity($catalog, 'Product', 'product', 'retailing.product', 0);
        $category->setPublished(true);
        $category->setWorkflowState('published');

        $draft = new CaseDraftEntity('actor-123', 'retailing.product');
        $draft->setCatalogCategory($category);

        return new CaseEntity($draft, $category);
    }
}
