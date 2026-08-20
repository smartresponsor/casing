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

    /** @return list<array{code: string, label: string}> */
    public function publishedTypes(string $catalogCode, string $categoryPath, string $tenant = 'default'): array
    {
        $category = $this->publishedCategory($catalogCode, $categoryPath, $tenant);
        if (!$category instanceof CatalogCategoryEntity) {
            return [];
        }

        $metadata = $category->getMetadata();
        if ('catalog-category-types@1' !== ($metadata['schema'] ?? null) || !is_array($metadata['types'] ?? null)) {
            return [];
        }

        $types = [];
        $seen = [];
        foreach ($metadata['types'] as $type) {
            if (!is_array($type)) {
                continue;
            }
            $code = strtolower(trim((string) ($type['code'] ?? '')));
            $label = trim((string) ($type['label'] ?? ''));
            if ('' === $code || '' === $label || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $types[] = ['code' => $code, 'label' => $label];
        }

        return $types;
    }

    public function isPublishedType(string $catalogCode, string $categoryPath, string $typeCode, string $tenant = 'default'): bool
    {
        $typeCode = trim($typeCode);
        foreach ($this->publishedTypes($catalogCode, $categoryPath, $tenant) as $type) {
            if ($type['code'] === $typeCode) {
                return true;
            }
        }

        return false;
    }
}
