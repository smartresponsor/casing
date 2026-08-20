<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Contract\LeadSubjectResolverInterface;
use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Contract\ServicePaymentSubjectResolverInterface;
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
        private ServicePaymentSubjectResolverInterface $servicePayments,
        private LeadSubjectResolverInterface $leadSubjects,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support', name: 'casing_support_home', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function __invoke(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $rows = [];
        $returnCategory = $this->catalogs->publishedCategory('products', 'products.return');
        $returnTypes = $this->catalogs->publishedTypes('products', 'products.return');
        if (null !== $returnCategory && [] !== $returnTypes) {
            $rows[] = [
                'id' => 'product-return',
                'context' => 'Product',
                'request' => $returnCategory->getName(),
                'description' => 'Request help returning a product from one of your orders.',
                'href' => '/support/product/return',
                'supportTypes' => $returnTypes,
                'availableItems' => count($this->subjects->listForActor($actorId)),
            ];
        }

        $disputeCategory = $this->catalogs->publishedCategory('services', 'services.dispute');
        $disputeTypes = $this->catalogs->publishedTypes('services', 'services.dispute');
        if (null !== $disputeCategory && [] !== $disputeTypes) {
            $rows[] = [
                'id' => 'service-dispute',
                'context' => 'Service',
                'request' => $disputeCategory->getName(),
                'description' => 'Open a dispute about a payment associated with one of your service orders.',
                'href' => '/support/service/dispute',
                'supportTypes' => $disputeTypes,
                'availableItems' => count($this->servicePayments->listForActor($actorId)),
            ];
        }

        $leadDisputeCategory = $this->catalogs->publishedCategory('leads', 'leads.dispute');
        $leadDisputeTypes = $this->catalogs->publishedTypes('leads', 'leads.dispute');
        if (null !== $leadDisputeCategory && [] !== $leadDisputeTypes) {
            $rows[] = [
                'id' => 'lead-dispute',
                'context' => 'Lead',
                'request' => $leadDisputeCategory->getName(),
                'description' => 'Dispute a lead that is already associated with your vendor relationship.',
                'href' => '/support/lead/dispute',
                'supportTypes' => $leadDisputeTypes,
                'availableItems' => count($this->leadSubjects->listForActor($actorId)),
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
                'headerActions' => [
                    ['label' => 'My cases', 'href' => '/support/case', 'variant' => 'default', 'operation' => 'index', 'enabled' => true, 'visibility' => 'visible'],
                ],
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
