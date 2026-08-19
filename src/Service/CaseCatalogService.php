<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;

final class CaseCatalogService
{
    public function __construct(
        private readonly CatalogCatalogTreeReadServiceInterface $catalogTrees,
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
}
