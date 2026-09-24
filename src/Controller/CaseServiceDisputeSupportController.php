<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\DTO\Claim\CaseServiceDisputeClaimDTO;
use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Form\Claim\CaseServiceDisputeClaimType;
use App\Casing\ResolverInterface\Paying\CaseServicePaymentSubjectResolverInterface;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Service\CaseIntakeService;
use App\Casing\Service\CaseServiceDisputeIntakeService;
use App\Casing\Value\CaseServicePaymentSubject;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CaseServiceDisputeSupportController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private CaseServicePaymentSubjectResolverInterface $subjects,
        private CaseCatalogService $catalogs,
        private CaseIntakeService $intake,
        private CaseServiceDisputeIntakeService $disputes,
        private FormFactoryInterface $forms,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute', name: 'casing_support_service_dispute', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function intake(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subjects = $this->subjects->listForActor($actorId);
        $types = $this->catalogs->publishedSupportTypes('retailing', 'retailing.service', 'dispute');
        $claim = new CaseServiceDisputeClaimDTO();
        $form = $this->forms->create(CaseServiceDisputeClaimType::class, $claim, ['subjects' => $subjects, 'types' => $types]);
        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof CaseServicePaymentSubject) {
                $category = $this->catalogs->publishedCategory('retailing', 'retailing.service');
                if (null === $category || !$this->catalogs->isPublishedSupportType('retailing', 'retailing.service', 'dispute', $claim->typeCode)) {
                    throw new \DomainException('Service dispute support is not currently available.');
                }
                $draft = $this->intake->start($actorId, 'retailing.service');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associatePayment($draft, $claim->subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, null);
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute/payment/{paymentReference}', name: 'casing_support_service_dispute_context', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function contextual(Request $request, string $paymentReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subject = $this->subjects->resolve($actorId, $paymentReference);
        if (!$subject instanceof CaseServicePaymentSubject) {
            throw new AccessDeniedHttpException('We could not associate this payment with your account.');
        }

        $types = $this->catalogs->publishedSupportTypes('retailing', 'retailing.service', 'dispute');
        $claim = new CaseServiceDisputeClaimDTO();
        $claim->subject = $subject;
        $form = $this->forms->create(CaseServiceDisputeClaimType::class, $claim, ['subjects' => [$subject], 'types' => $types]);
        if ($request->isMethod('POST')) {
            $payload = $this->requestPayload($request);
            $payload['subject'] = hash('sha256', $subject->paymentReference);
            $form->submit($payload);
            if ($form->isValid()) {
                $category = $this->catalogs->publishedCategory('retailing', 'retailing.service');
                if (null === $category || !$this->catalogs->isPublishedSupportType('retailing', 'retailing.service', 'dispute', $claim->typeCode)) {
                    throw new \DomainException('Service dispute support is not currently available.');
                }

                $draft = $this->intake->start($actorId, 'retailing.service');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associatePayment($draft, $subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        $payload = $this->formPayload($claim, [$subject], null);
        $payload['data']['action'] = sprintf('/support/service/dispute/payment/%s', rawurlencode($paymentReference));
        $payload['data']['contextLocked'] = true;
        $payload['data']['verifiedContext'] = $subject->toArray();

        return $payload;
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute/{draftReference}', name: 'casing_support_service_dispute_review', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function review(Request $request, string $draftReference): array
    {
        return $this->reviewPayload($this->requireDraft($draftReference, $this->actors->requireActorId($request)));
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute/{draftReference}/edit', name: 'casing_support_service_dispute_edit', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function edit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $draft = $this->requireDraft($draftReference, $actorId);
        $subjects = $this->subjects->listForActor($actorId);
        $types = $this->catalogs->publishedSupportTypes('retailing', 'retailing.service', 'dispute');
        $claim = $this->claimFromDraft($draft, $subjects);
        $form = $this->forms->create(CaseServiceDisputeClaimType::class, $claim, ['subjects' => $subjects, 'types' => $types]);
        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof CaseServicePaymentSubject) {
                if (!$this->catalogs->isPublishedSupportType('retailing', 'retailing.service', 'dispute', $claim->typeCode)) {
                    throw new \DomainException('Service dispute support is not currently available.');
                }
                $this->disputes->associatePayment($draft, $claim->subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, $draft->getDraftReference());
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute/{draftReference}/submit', name: 'casing_support_service_dispute_submit', methods: ['POST'], defaults: ['_view_controlled' => true])]
    public function submit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $this->requireDraft($draftReference, $actorId);
        $case = $this->intake->submit($draftReference, $actorId);

        return [
            '_view' => $this->view('show', 'success'),
            'interface' => $this->content('Service dispute submitted', 'Your case has been created and is ready for processing.'),
            'data' => ['caseReference' => $case->getCaseReference(), 'status' => $case->getStatus()->value],
            'meta' => ['title' => 'Service dispute submitted'],
        ];
    }

    private function requireDraft(string $draftReference, string $actorId): CaseDraftEntity
    {
        $draft = $this->intake->resume($draftReference, $actorId);
        if (!$draft instanceof CaseDraftEntity || 'retailing.service' !== $draft->getBusinessContext() || 'service' !== $draft->getCatalogCategory()?->getSlug()) {
            throw new AccessDeniedHttpException('We could not associate this case draft with your account.');
        }

        return $draft;
    }

    /** @param list<CaseServicePaymentSubject> $subjects */
    private function claimFromDraft(CaseDraftEntity $draft, array $subjects): CaseServiceDisputeClaimDTO
    {
        $claim = new CaseServiceDisputeClaimDTO();
        $stored = $draft->getContributionData()['paying.service_dispute_subject'] ?? [];
        $paymentReference = is_array($stored) ? (string) ($stored['paymentReference'] ?? '') : '';
        foreach ($subjects as $subject) {
            if ($subject->paymentReference === $paymentReference) {
                $claim->subject = $subject;
                break;
            }
        }
        $catalogType = $draft->getContributionData()['cataloging.support_type'] ?? [];
        $claim->typeCode = is_array($catalogType) ? (string) ($catalogType['typeCode'] ?? '') : '';
        $facts = $draft->getSuppliedFacts()['serviceDispute'] ?? [];
        $claim->description = is_array($facts) ? (string) ($facts['description'] ?? '') : '';

        return $claim;
    }

    /** @param list<CaseServicePaymentSubject> $subjects
     * @return array<string, mixed>
     */
    private function formPayload(CaseServiceDisputeClaimDTO $claim, array $subjects, ?string $draftReference): array
    {
        $options = array_map(static fn (CaseServicePaymentSubject $subject): array => [
            'label' => sprintf('%s · %s %s · %s', $subject->orderNumber, $subject->amount, $subject->currency, $subject->status),
            'value' => hash('sha256', $subject->paymentReference),
        ], $subjects);
        $typeOptions = array_map(
            static fn (array $type): array => ['label' => $type['label'], 'value' => $type['code']],
            $this->catalogs->publishedSupportTypes('retailing', 'retailing.service', 'dispute'),
        );

        return [
            '_view' => $this->view(null === $draftReference ? 'create' : 'edit', 'form'),
            'interface' => $this->content('Service dispute', 'Select a payment from one of your orders and describe the dispute.'),
            'data' => [
                'draftReference' => $draftReference,
                'action' => null === $draftReference ? '/support/service/dispute' : sprintf('/support/service/dispute/%s/edit', $draftReference),
                'method' => 'POST',
                'formFields' => [
                    ['nameEntity' => 'subject', 'label' => 'Payment', 'type' => 'select', 'value' => $claim->subject instanceof CaseServicePaymentSubject ? hash('sha256', $claim->subject->paymentReference) : null, 'required' => true, 'options' => $options],
                    ['nameEntity' => 'typeCode', 'label' => 'Dispute type', 'type' => 'select', 'value' => $claim->typeCode, 'required' => true, 'options' => $typeOptions],
                    ['nameEntity' => 'description', 'label' => 'Describe the dispute', 'type' => 'textarea', 'value' => $claim->description, 'required' => true, 'options' => []],
                ],
            ],
            'meta' => ['title' => 'Service dispute'],
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(CaseDraftEntity $draft): array
    {
        return [
            '_view' => $this->view('show', 'review'),
            'interface' => $this->content('Review service dispute', 'Verified payment context is shown separately from your statement.'),
            'data' => [
                'draftReference' => $draft->getDraftReference(),
                'supportType' => $draft->getContributionData()['cataloging.support_type'] ?? null,
                'verifiedContext' => $draft->getContributionData()['paying.service_dispute_subject'] ?? null,
                'suppliedFacts' => $draft->getSuppliedFacts()['serviceDispute'] ?? null,
                'headerActions' => [
                    ['label' => 'Back', 'href' => sprintf('/support/service/dispute/%s/edit', $draft->getDraftReference()), 'variant' => 'default', 'operation' => 'edit', 'enabled' => true, 'visibility' => 'visible'],
                    ['label' => 'Submit', 'href' => sprintf('/support/service/dispute/%s/submit', $draft->getDraftReference()), 'variant' => 'primary', 'operation' => 'submit', 'method' => 'POST', 'enabled' => true, 'visibility' => 'visible'],
                ],
            ],
            'meta' => ['title' => 'Review service dispute'],
        ];
    }

    /** @return array<string, mixed> */
    private function requestPayload(Request $request): array
    {
        $payload = $request->request->all('service_dispute_claim');
        if ([] !== $payload) {
            return $payload;
        }

        return array_intersect_key($request->request->all(), array_flip(['subject', 'typeCode', 'description']));
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
