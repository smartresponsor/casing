<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCenterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CaseCenterController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private CaseCenterService $cases,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/case', name: 'casing_case_center', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function index(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $rows = array_map(fn (CaseEntity $case): array => $this->row($case), $this->cases->listForActor($actorId));

        return [
            '_view' => $this->view('index', 'case-center'),
            'interface' => $this->content('My cases', 'Track support cases and respond when more information is requested.'),
            'data' => [
                'columns' => [
                    ['key' => 'reference', 'label' => 'Case', 'type' => 'text'],
                    ['key' => 'context', 'label' => 'Context', 'type' => 'text'],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ],
                'rows' => $rows,
            ],
            'meta' => ['title' => 'My cases'],
        ];
    }

    /** @return array<string, mixed> */
    #[Route('/support/case/{caseReference}', name: 'casing_case_center_show', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function show(Request $request, string $caseReference): array
    {
        $case = $this->cases->requireActorCase($caseReference, $this->actors->requireActorId($request));

        return $this->detailPayload($case);
    }

    /** @return array<string, mixed> */
    #[Route('/support/case/{caseReference}/information', name: 'casing_case_center_information', methods: ['POST'], defaults: ['_view_controlled' => true])]
    public function information(Request $request, string $caseReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $payload = $request->getPayload()->all();
        $formPayload = is_array($payload['case_information'] ?? null) ? $payload['case_information'] : [];
        $message = (string) ($formPayload['message'] ?? $payload['message'] ?? '');
        $case = $this->cases->provideInformation($caseReference, $actorId, $message);

        return $this->detailPayload($case);
    }

    /** @return array<string, mixed> */
    private function detailPayload(CaseEntity $case): array
    {
        $actions = [];
        $informationRequest = $this->cases->openInformationRequest($case);
        $source = $this->sourceContext($case);
        if (null !== $source) {
            $actions[] = ['label' => 'Source', 'href' => $source, 'variant' => 'default', 'operation' => 'show', 'enabled' => true, 'visibility' => 'visible'];
        }

        return [
            '_view' => $this->view('show', 'case-detail'),
            'interface' => $this->content('Case '.$case->getCaseReference(), 'Support case details and current lifecycle state.'),
            'data' => [
                'reference' => $case->getCaseReference(),
                'status' => $case->getStatus()->value,
                'businessContext' => $case->getBusinessContext(),
                'category' => $case->getCatalogCategory()->getPath(),
                'description' => $case->getDescription(),
                'verifiedContext' => $case->getContributionData(),
                'suppliedFacts' => $case->getSuppliedFacts(),
                'attachmentReferences' => $case->getAttachmentReferences(),
                'sourceReferences' => $case->getSubjectReferences(),
                'informationRequest' => null !== $informationRequest ? [
                    'question' => $informationRequest->getQuestion(),
                    'requestedAt' => $informationRequest->getRequestedAt()->format(DATE_ATOM),
                ] : null,
                'informationForm' => CaseStatus::NeedsInformation === $case->getStatus() && null !== $informationRequest ? [
                    'action' => sprintf('/support/case/%s/information', rawurlencode($case->getCaseReference())),
                    'method' => 'POST',
                    'fields' => [[
                        'nameEntity' => 'message',
                        'label' => 'Your response',
                        'type' => 'textarea',
                        'value' => '',
                        'required' => true,
                        'options' => [],
                    ]],
                ] : null,
                'headerActions' => $actions,
            ],
            'meta' => ['title' => 'Case '.$case->getCaseReference()],
        ];
    }

    /** @return array<string, mixed> */
    private function row(CaseEntity $case): array
    {
        return [
            'id' => $case->getCaseReference(),
            'reference' => $case->getCaseReference(),
            'context' => $case->getBusinessContext(),
            'category' => $case->getCatalogCategory()->getPath(),
            'status' => $case->getStatus()->value,
            'href' => sprintf('/support/case/%s', rawurlencode($case->getCaseReference())),
        ];
    }

    private function sourceContext(CaseEntity $case): ?string
    {
        $references = $case->getSubjectReferences();
        $order = null;
        $item = null;
        $payment = null;
        $lead = null;
        foreach ($references as $reference) {
            $component = $reference['component'] ?? '';
            $type = $reference['type'] ?? '';
            $id = $reference['id'] ?? '';
            if ('ordering' === $component && 'order' === $type) {
                $order = $id;
            } elseif ('ordering' === $component && 'order-item' === $type) {
                $item = $id;
            } elseif ('paying' === $component && 'payment' === $type) {
                $payment = $id;
            } elseif ('relating' === $component && 'lead' === $type) {
                $lead = $id;
            }
        }

        if (is_string($lead) && '' !== $lead) {
            return '/support/lead/dispute/lead/'.rawurlencode($lead);
        }
        if (is_string($payment) && '' !== $payment) {
            return '/support/service/dispute/payment/'.rawurlencode($payment);
        }
        if (is_string($order) && '' !== $order && is_string($item) && '' !== $item) {
            return sprintf('/support/product/return/order/%s/item/%s', rawurlencode($order), rawurlencode($item));
        }
        if (is_string($order) && '' !== $order) {
            return '/support/order/'.rawurlencode($order);
        }

        return null;
    }

    /** @return array<string, string> */
    private function view(string $operation, string $intent): array
    {
        return ['surface' => 'support', 'operation' => $operation, 'intent' => $intent, 'format' => 'auto', 'component' => 'Casing'];
    }

    /** @return array{locations: array<string, list<array<string, string>>>} */
    private function content(string $label, string $description): array
    {
        return ['locations' => ['shell.main.content' => [['type' => 'text', 'label' => $label, 'description' => $description]]]];
    }
}
