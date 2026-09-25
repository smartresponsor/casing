<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Controller\CaseSupportHomeController;
use App\Casing\ResolverInterface\Ordering\CasePurchasedProductSubjectResolverInterface;
use App\Casing\ResolverInterface\Paying\CaseServicePaymentSubjectResolverInterface;
use App\Casing\ResolverInterface\Relating\CaseLeadSubjectResolverInterface;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class CaseSupportHomeControllerTest extends TestCase
{
    public function testSupportHomeRequiresPublishedTypeVocabularyAndExposesIt(): void
    {
        $retailingCatalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $product = new CatalogCategoryEntity($retailingCatalog, 'Product', 'product', 'retailing.product', 0);
        $product->setPublished(true);
        $product->setWorkflowState('published');
        $product->setMetadata([
            'schema' => 'retailing-category@1',
            'support' => [
                'return' => [
                    'label' => 'Return',
                    'types' => [['code' => 'damaged', 'label' => 'Damaged']],
                ],
            ],
        ]);

        $service = new CatalogCategoryEntity($retailingCatalog, 'Service', 'service', 'retailing.service', 0);
        $service->setPublished(true);
        $service->setWorkflowState('published');
        $service->setMetadata(['schema' => 'retailing-category@1', 'support' => []]);

        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $lookup->method('publishedByCatalogAndPath')->willReturnCallback(
            static fn (string $catalogCode, string $path): ?CatalogCategoryEntity => match ($catalogCode.'.'.$path) {
                'retailing.retailing.product' => $product,
                'retailing.retailing.service' => $service,
                default => null,
            },
        );
        $catalogs = new CaseCatalogService($this->createStub(CatalogCatalogTreeReadServiceInterface::class), $lookup);

        $products = $this->createStub(CasePurchasedProductSubjectResolverInterface::class);
        $products->method('listForActor')->willReturn([]);
        $payments = $this->createStub(CaseServicePaymentSubjectResolverInterface::class);
        $payments->method('listForActor')->willReturn([]);
        $leads = $this->createStub(CaseLeadSubjectResolverInterface::class);
        $leads->method('listForActor')->willReturn([]);

        $controller = new CaseSupportHomeController(
            new CaseActorAccessService($this->createStub(Security::class)),
            $catalogs,
            $products,
            $payments,
            $leads,
        );
        $request = new Request();
        $request->attributes->set('casing_actor_id', 'actor-1');

        $payload = $controller($request);

        self::assertCount(1, $payload['data']['rows']);
        self::assertSame('product-return', $payload['data']['rows'][0]['id']);
        self::assertSame('Product', $payload['data']['rows'][0]['context']);
        self::assertSame('Return', $payload['data']['rows'][0]['request']);
        self::assertSame([['code' => 'damaged', 'label' => 'Damaged']], $payload['data']['rows'][0]['supportTypes']);
    }

    public function testSupportHomeExposesAllPublishedSupportContextsWithActorScopedCounts(): void
    {
        $retailingCatalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $product = new CatalogCategoryEntity($retailingCatalog, 'Product', 'product', 'retailing.product', 0);
        $product->setPublished(true);
        $product->setWorkflowState('published');
        $product->setMetadata([
            'schema' => 'retailing-category@1',
            'support' => [
                'return' => [
                    'label' => 'Return',
                    'types' => [['code' => 'damaged', 'label' => 'Damaged']],
                ],
            ],
        ]);

        $service = new CatalogCategoryEntity($retailingCatalog, 'Service', 'service', 'retailing.service', 0);
        $service->setPublished(true);
        $service->setWorkflowState('published');
        $service->setMetadata([
            'schema' => 'retailing-category@1',
            'support' => [
                'dispute' => [
                    'label' => 'Dispute',
                    'types' => [['code' => 'billing', 'label' => 'Billing']],
                ],
            ],
        ]);

        $leadCatalog = new CatalogCatalogEntity('leads', 'Leads', 'lead-classification');
        $leadDispute = new CatalogCategoryEntity($leadCatalog, 'Lead dispute', 'dispute', 'leads.dispute', 0);
        $leadDispute->setPublished(true);
        $leadDispute->setWorkflowState('published');
        $leadDispute->setMetadata([
            'schema' => 'catalog-category-types@1',
            'types' => [['code' => 'invalid', 'label' => 'Invalid lead']],
        ]);

        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $lookup->method('publishedByCatalogAndPath')->willReturnCallback(
            static fn (string $catalogCode, string $path): ?CatalogCategoryEntity => match ($catalogCode.'.'.$path) {
                'retailing.retailing.product' => $product,
                'retailing.retailing.service' => $service,
                'leads.leads.dispute' => $leadDispute,
                default => null,
            },
        );
        $catalogs = new CaseCatalogService($this->createStub(CatalogCatalogTreeReadServiceInterface::class), $lookup);

        $products = $this->createMock(CasePurchasedProductSubjectResolverInterface::class);
        $products->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([null, null]);
        $payments = $this->createMock(CaseServicePaymentSubjectResolverInterface::class);
        $payments->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([null]);
        $leads = $this->createMock(CaseLeadSubjectResolverInterface::class);
        $leads->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([null, null, null]);

        $controller = new CaseSupportHomeController(
            new CaseActorAccessService($this->createStub(Security::class)),
            $catalogs,
            $products,
            $payments,
            $leads,
        );
        $request = new Request(attributes: ['casing_actor_id' => 'actor-1']);

        $payload = $controller($request);

        self::assertSame(['product-return', 'service-dispute', 'lead-dispute'], array_column($payload['data']['rows'], 'id'));
        self::assertSame([2, 1, 3], array_column($payload['data']['rows'], 'availableItems'));
        self::assertSame([['code' => 'billing', 'label' => 'Billing']], $payload['data']['rows'][1]['supportTypes']);
        self::assertSame([['code' => 'invalid', 'label' => 'Invalid lead']], $payload['data']['rows'][2]['supportTypes']);
        self::assertSame('Support', $payload['meta']['title']);
        self::assertSame('/support/order', $payload['data']['headerActions'][1]['href']);
    }
}
