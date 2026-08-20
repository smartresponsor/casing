<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Contract\LeadSubjectResolverInterface;
use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Contract\ServicePaymentSubjectResolverInterface;
use App\Casing\Controller\SupportHomeController;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class SupportHomeControllerTest extends TestCase
{
    public function testSupportHomeRequiresPublishedTypeVocabularyAndExposesIt(): void
    {
        $productCatalog = new CatalogCatalogEntity('products', 'Products', 'product-commerce');
        $productReturn = new CatalogCategoryEntity($productCatalog, 'Return', 'return', 'products.return', 1);
        $productReturn->setPublished(true);
        $productReturn->setWorkflowState('published');
        $productReturn->setMetadata([
            'schema' => 'catalog-category-types@1',
            'types' => [['code' => 'damaged', 'label' => 'Damaged']],
        ]);

        $serviceCatalog = new CatalogCatalogEntity('services', 'Services', 'service-discovery');
        $serviceDispute = new CatalogCategoryEntity($serviceCatalog, 'Dispute', 'dispute', 'services.dispute', 1);
        $serviceDispute->setPublished(true);
        $serviceDispute->setWorkflowState('published');

        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $lookup->method('publishedByCatalogAndPath')->willReturnCallback(
            static fn (string $catalogCode, string $path): ?CatalogCategoryEntity => match ($catalogCode.'.'.$path) {
                'products.products.return' => $productReturn,
                'services.services.dispute' => $serviceDispute,
                default => null,
            },
        );
        $catalogs = new CaseCatalogService($this->createStub(CatalogCatalogTreeReadServiceInterface::class), $lookup);

        $products = $this->createStub(PurchasedProductSubjectResolverInterface::class);
        $products->method('listForActor')->willReturn([]);
        $payments = $this->createStub(ServicePaymentSubjectResolverInterface::class);
        $payments->method('listForActor')->willReturn([]);
        $leads = $this->createStub(LeadSubjectResolverInterface::class);
        $leads->method('listForActor')->willReturn([]);

        $controller = new SupportHomeController(
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
        self::assertSame([['code' => 'damaged', 'label' => 'Damaged']], $payload['data']['rows'][0]['supportTypes']);
    }
}
