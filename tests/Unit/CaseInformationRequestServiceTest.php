<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Entity\CaseEntity;
use App\Casing\Entity\CaseInformationRequestEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\RepositoryInterface\CaseInformationRequestRepositoryInterface;
use App\Casing\RepositoryInterface\CaseRepositoryInterface;
use App\Casing\Service\CaseInformationRequestService;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use PHPUnit\Framework\TestCase;

final class CaseInformationRequestServiceTest extends TestCase
{
    public function testRequestCreatesOpenRequestAndMovesProcessingCaseToNeedsInformation(): void
    {
        $case = $this->caseInProcessing();
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createMock(CaseRepositoryInterface::class);

        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn(null);
        $requests->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(CaseInformationRequestEntity::class), false);
        $cases->expects(self::once())->method('save')->with($case);

        $service = new CaseInformationRequestService($requests, $cases);
        $request = $service->request($case, ' Please provide the invoice. ');

        self::assertSame('Please provide the invoice.', $request->getQuestion());
        self::assertSame(CaseStatus::NeedsInformation, $case->getStatus());
    }

    public function testRequestRejectsCaseThatCannotEnterNeedsInformation(): void
    {
        $case = $this->case();
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $requests->expects(self::never())->method('findOpenForCase');
        $requests->expects(self::never())->method('save');
        $cases->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        (new CaseInformationRequestService($requests, $cases))->request($case, 'Need more information.');
    }

    public function testRequestRejectsSecondOpenInformationRequest(): void
    {
        $case = $this->caseInProcessing();
        $existing = new CaseInformationRequestEntity($case, 'Existing question.');
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createMock(CaseRepositoryInterface::class);

        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn($existing);
        $requests->expects(self::never())->method('save');
        $cases->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        (new CaseInformationRequestService($requests, $cases))->request($case, 'Second question.');
    }

    public function testAnswerClosesOpenRequestAndReturnsCaseToProcessing(): void
    {
        $case = $this->caseInProcessing();
        $case->transitionToAllowed(CaseStatus::NeedsInformation);
        $request = new CaseInformationRequestEntity($case, 'Need invoice.');

        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createMock(CaseRepositoryInterface::class);

        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn($request);
        $requests->expects(self::once())->method('save')->with($request, false);
        $cases->expects(self::once())->method('save')->with($case);

        $service = new CaseInformationRequestService($requests, $cases);
        self::assertSame($request, $service->answer($case, ' Invoice 123. '));
        self::assertSame('Invoice 123.', $request->getAnswer());
        self::assertSame([['message' => ' Invoice 123. ']], $case->getSuppliedFacts()['followUp']);
        self::assertSame(CaseStatus::Processing, $case->getStatus());
    }

    public function testAnswerRejectsCaseThatIsNotWaitingForInformation(): void
    {
        $case = $this->caseInProcessing();
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createStub(CaseRepositoryInterface::class);
        $requests->expects(self::never())->method('findOpenForCase');

        $this->expectException(\DomainException::class);
        (new CaseInformationRequestService($requests, $cases))->answer($case, 'Unexpected answer.');
    }

    public function testAnswerRejectsMissingOpenRequest(): void
    {
        $case = $this->caseInProcessing();
        $case->transitionToAllowed(CaseStatus::NeedsInformation);
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $cases = $this->createMock(CaseRepositoryInterface::class);
        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn(null);
        $requests->expects(self::never())->method('save');
        $cases->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        (new CaseInformationRequestService($requests, $cases))->answer($case, 'Missing request.');
    }

    public function testOpenForCaseDelegatesToRepository(): void
    {
        $case = $this->case();
        $request = new CaseInformationRequestEntity($case, 'Question.');
        $requests = $this->createMock(CaseInformationRequestRepositoryInterface::class);
        $requests->expects(self::once())->method('findOpenForCase')->with($case)->willReturn($request);

        $service = new CaseInformationRequestService($requests, $this->createStub(CaseRepositoryInterface::class));

        self::assertSame($request, $service->openForCase($case));
    }

    private function caseInProcessing(): CaseEntity
    {
        $case = $this->case();
        $case->transitionToAllowed(CaseStatus::Processing);

        return $case;
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
