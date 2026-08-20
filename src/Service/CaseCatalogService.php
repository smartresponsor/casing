<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;

final class CaseCatalogService
{
    public function __construct(
        private readonly CatalogCatalogTreeReadServiceInterface $catalogTrees,
        private readonly CatalogCategoryLookupServiceInterface $categoryLookup,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function publishedContext(string $catalogCode, string $tenant = 'default'): ?array
    {
        $catalogCode = trim($catalogCode);
        if ('' === $catalogCode) {
            return null;
        }

        return $this->catalogTrees->byCode($catalogCode, $tenant);
    }

    public function contextExists(string $catalogCode, string $tenant = 'default'): bool
    {
        return null !== $this->publishedContext($catalogCode, $tenant);
    }

    public function publishedCategory(string $catalogCode, string $path, string $tenant = 'default'): ?CatalogCategoryEntity
    {
        return $this->categoryLookup->publishedByCatalogAndPath($catalogCode, $path, $tenant);
    }
}
