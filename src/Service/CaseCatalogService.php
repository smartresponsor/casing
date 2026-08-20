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

    /** @return list<array{title: string, path: string, slug: string}> */
    public function publishedChildren(string $catalogCode, string $parentPath, string $tenant = 'default'): array
    {
        $context = $this->publishedContext($catalogCode, $tenant);
        if (!is_array($context['root'] ?? null)) {
            return [];
        }

        $parent = $this->findNodeByPath($context['root'], $parentPath);
        if (!is_array($parent)) {
            return [];
        }

        $children = [];
        foreach ($parent['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $title = trim((string) ($child['title'] ?? ''));
            $path = trim((string) ($child['path'] ?? ''));
            $slug = trim((string) ($child['slug'] ?? ''));
            if ('' !== $title && '' !== $path && '' !== $slug) {
                $children[] = ['title' => $title, 'path' => $path, 'slug' => $slug];
            }
        }

        return $children;
    }

    /** @param array<string, mixed> $node
     * @return array<string, mixed>|null
     */
    private function findNodeByPath(array $node, string $path): ?array
    {
        if ($path === (string) ($node['path'] ?? '')) {
            return $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $found = $this->findNodeByPath($child, $path);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }
}
