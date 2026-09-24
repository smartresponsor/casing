<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Service\CaseCatalogService;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;
use PHPUnit\Framework\TestCase;

final class CaseCatalogServiceTest extends TestCase
{
    public function testPublishedContextTrimsCodeAndRejectsBlankCode(): void
    {
        $trees = $this->createMock(CatalogCatalogTreeReadServiceInterface::class);
        $trees->expects(self::once())->method('byCode')->with('retailing', 'tenant-a')->willReturn(['code' => 'retailing']);
        $service = new CaseCatalogService($trees, $this->createStub(CatalogCategoryLookupServiceInterface::class));

        self::assertSame(['code' => 'retailing'], $service->publishedContext(' retailing ', 'tenant-a'));
        self::assertNull($service->publishedContext('   '));
    }

    public function testContextExistsReflectsPublishedTreeAvailability(): void
    {
        $trees = $this->createStub(CatalogCatalogTreeReadServiceInterface::class);
        $trees->method('byCode')->willReturnMap([
            ['retailing', 'default', ['code' => 'retailing']],
            ['missing', 'default', null],
        ]);
        $service = new CaseCatalogService($trees, $this->createStub(CatalogCategoryLookupServiceInterface::class));

        self::assertTrue($service->contextExists('retailing'));
        self::assertFalse($service->contextExists('missing'));
    }

    public function testPublishedCategoryDelegatesToLookup(): void
    {
        $category = $this->category('retailing.product');
        $lookup = $this->createMock(CatalogCategoryLookupServiceInterface::class);
        $lookup->expects(self::once())
            ->method('publishedByCatalogAndPath')
            ->with('retailing', 'retailing.product', 'tenant-b')
            ->willReturn($category);

        $service = new CaseCatalogService($this->createStub(CatalogCatalogTreeReadServiceInterface::class), $lookup);

        self::assertSame($category, $service->publishedCategory('retailing', 'retailing.product', 'tenant-b'));
    }

    public function testPublishedTypesNormalizesFiltersAndDeduplicatesMetadata(): void
    {
        $category = $this->category('leads.dispute');
        $category->setMetadata([
            'schema' => 'catalog-category-types@1',
            'types' => [
                ['code' => ' Invalid ', 'label' => ' Invalid lead '],
                ['code' => 'INVALID', 'label' => 'Duplicate must be ignored'],
                ['code' => '', 'label' => 'Missing code'],
                ['code' => 'fraud', 'label' => ''],
                'not-an-array',
                ['code' => 'fraud', 'label' => 'Fraud'],
            ],
        ]);

        $service = $this->serviceForCategory($category);

        self::assertSame([
            ['code' => 'invalid', 'label' => 'Invalid lead'],
            ['code' => 'fraud', 'label' => 'Fraud'],
        ], $service->publishedTypes('leads', 'leads.dispute'));
        self::assertTrue($service->isPublishedType('leads', 'leads.dispute', ' INVALID '));
        self::assertFalse($service->isPublishedType('leads', 'leads.dispute', 'missing'));
    }

    public function testPublishedTypesRejectsMissingOrWrongMetadataSchema(): void
    {
        $category = $this->category('leads.dispute');
        $service = $this->serviceForCategory($category);
        self::assertSame([], $service->publishedTypes('leads', 'leads.dispute'));

        $category->setMetadata(['schema' => 'wrong', 'types' => [['code' => 'x', 'label' => 'X']]]);
        self::assertSame([], $service->publishedTypes('leads', 'leads.dispute'));
    }

    public function testPublishedSupportDefinitionExposesNormalizedTypesAndLabel(): void
    {
        $category = $this->category('retailing.product');
        $category->setMetadata([
            'schema' => 'retailing-category@1',
            'support' => [
                'return' => [
                    'label' => ' Product return ',
                    'types' => [
                        ['code' => ' Damaged ', 'label' => ' Damaged '],
                        ['code' => 'DAMAGED', 'label' => 'Duplicate'],
                        ['code' => 'wrong-item', 'label' => 'Wrong item'],
                    ],
                ],
            ],
        ]);

        $service = $this->serviceForCategory($category);

        self::assertSame('Product return', $service->publishedSupportLabel('retailing', 'retailing.product', ' RETURN '));
        self::assertSame([
            ['code' => 'damaged', 'label' => 'Damaged'],
            ['code' => 'wrong-item', 'label' => 'Wrong item'],
        ], $service->publishedSupportTypes('retailing', 'retailing.product', 'return'));
        self::assertTrue($service->isPublishedSupportType('retailing', 'retailing.product', 'return', ' DAMAGED '));
        self::assertFalse($service->isPublishedSupportType('retailing', 'retailing.product', 'return', 'missing'));
    }

    public function testPublishedSupportReturnsEmptyValuesWhenDefinitionIsUnavailable(): void
    {
        $category = $this->category('retailing.product');
        $category->setMetadata(['schema' => 'wrong', 'support' => []]);
        $service = $this->serviceForCategory($category);

        self::assertSame([], $service->publishedSupportTypes('retailing', 'retailing.product', 'return'));
        self::assertNull($service->publishedSupportLabel('retailing', 'retailing.product', 'return'));
    }

    private function serviceForCategory(CatalogCategoryEntity $category): CaseCatalogService
    {
        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $lookup->method('publishedByCatalogAndPath')->willReturn($category);

        return new CaseCatalogService($this->createStub(CatalogCatalogTreeReadServiceInterface::class), $lookup);
    }

    private function category(string $path): CatalogCategoryEntity
    {
        $catalog = new CatalogCatalogEntity('retailing', 'Retailing', 'retailing-classification');
        $category = new CatalogCategoryEntity($catalog, 'Category', 'category', $path, 1);
        $category->setPublished(true);
        $category->setWorkflowState('published');

        return $category;
    }
}
