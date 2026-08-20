<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SupportHomeController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private CaseCatalogService $catalogs,
        private PurchasedProductSubjectResolverInterface $subjects,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support', name: 'casing_support_home', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function __invoke(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $rows = [];
        $returnCategory = $this->catalogs->publishedCategory('products', 'products.return');
        if (null !== $returnCategory) {
            $rows[] = [
                'id' => 'product-return',
                'context' => 'Product',
                'request' => $returnCategory->getName(),
                'description' => 'Request help returning a product from one of your orders.',
                'href' => '/support/product/return',
                'availableItems' => count($this->subjects->listForActor($actorId)),
            ];
        }

        return [
            '_view' => [
                'surface' => 'support',
                'operation' => 'index',
                'intent' => 'home',
                'format' => 'auto',
                'component' => 'Casing',
            ],
            'interface' => [
                'locations' => [
                    'shell.main.content' => [[
                        'type' => 'text',
                        'label' => 'Support',
                        'description' => 'Choose what you need help with. Available options come from platform-owned business contexts.',
                    ]],
                ],
            ],
            'data' => [
                'columns' => [
                    ['key' => 'context', 'label' => 'Context', 'type' => 'text'],
                    ['key' => 'request', 'label' => 'Request', 'type' => 'text'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
                    ['key' => 'availableItems', 'label' => 'Available items', 'type' => 'number'],
                ],
                'rows' => $rows,
            ],
            'meta' => ['title' => 'Support'],
        ];
    }
}
