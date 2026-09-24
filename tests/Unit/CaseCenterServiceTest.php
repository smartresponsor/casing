<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Entity\CaseEntity;
use App\Casing\Entity\CaseInformationRequestEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\RepositoryInterface\CaseInformationRequestRepositoryInterface;
use App\Casing\RepositoryInterface\CaseRepositoryInterface;
use App\Casing\Service\CaseCenterService;
use App\Casing\Service\CaseInformationRequestService;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use PHPUnit\Framework\TestCase;

final class CaseCenterServiceTest extends TestCase
{
    public function testListForActorDelegatesToActorScopedRepositoryQuery(): void
    {
        $case = $this->case();
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $cases->expects(self::once())->method('findActorCases')->with('actor-123')->willReturn([$case]);

        $service = new CaseCenterService($cases, $this->informationRequests());

        self::assertSame([$case], $service->listForActor('actor-123'));
    }

    public function testRequireActorCaseTrimsIdentifiersAndReturnsOwnedCase(): void
    {
        $case = $this->case();
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $cases->expects(self::once())
            ->method('findActorCase')
            ->with($case->getCaseReference(), 'actor-123')
            ->willReturn($case);

        $service = new CaseCenterService($cases, $this->informationRequests());

        self::assertSame($case, $service->requireActorCase(' '.$case->getCaseReference().' ', ' actor-123 '));
    }

    public function testRequireActorCaseRejectsUnknownOrForeignCase(): void
    {
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $cases->expects(self::once())->method('findActorCase')->with('missing', 'actor-123')->willReturn(null);

        $service = new CaseCenterService($cases, $this->informationRequests());

        $this->expectException(\DomainException::class);
        $service->requireActorCase(' missing ', ' actor-123 ');
    }

    public function testProvideInformationAnswersOpenRequestAndReturnsCase(): void
    {
        $case = $this->case();
        $case->transitionToAllowed(CaseStatus::Processing);
        $case->transitionToAllowed(CaseStatus::NeedsInformation);
        $request = new CaseInformationRequestEntity($case, 'Need invoice.');

        $cases = $this->createMock(CaseRepositoryInterface::class);
        $cases->expects(self::once())
            ->method('findActorCase')
            ->with($case->getCaseReference(), 'actor-123')
            ->willReturn($case);
        $cases->expects(self::once())->method('save')->with($case);

        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn($request);
        $requests->expects(self::once())->method('save')->with($request, false);

        $service = new CaseCenterService($cases, new CaseInformationRequestService($requests, $cases));

        self::assertSame($case, $service->provideInformation($case->getCaseReference(), 'actor-123', 'Invoice 123'));
        self::assertSame(CaseStatus::Processing, $case->getStatus());
        self::assertSame('Invoice 123', $request->getAnswer());
    }

    public function testOpenInformationRequestDelegatesToInformationRequestService(): void
    {
        $case = $this->case();
        $request = new CaseInformationRequestEntity($case, 'Question.');
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn($request);

        $service = new CaseCenterService(
            $this->createStub(CaseRepositoryInterface::class),
            new CaseInformationRequestService($requests, $this->createStub(CaseRepositoryInterface::class)),
        );

        self::assertSame($request, $service->openInformationRequest($case));
    }

    private function informationRequests(): CaseInformationRequestService
    {
        return new CaseInformationRequestService(
            $this->createStub(CaseInformationRequestRepositoryInterface::class),
            $this->createStub(CaseRepositoryInterface::class),
        );
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
