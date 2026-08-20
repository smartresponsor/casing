<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Contract\ServicePaymentSubjectResolverInterface;
use App\Casing\Dto\ServiceDisputeClaimData;
use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Form\ServiceDisputeClaimType;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Service\CaseIntakeService;
use App\Casing\Service\ServiceDisputeIntakeService;
use App\Casing\Value\ServicePaymentSubject;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ServiceDisputeSupportController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private ServicePaymentSubjectResolverInterface $subjects,
        private CaseCatalogService $catalogs,
        private CaseIntakeService $intake,
        private ServiceDisputeIntakeService $disputes,
        private FormFactoryInterface $forms,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/dispute', name: 'casing_support_service_dispute', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function intake(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subjects = $this->subjects->listForActor($actorId);
        $claim = new ServiceDisputeClaimData();
        $form = $this->forms->create(ServiceDisputeClaimType::class, $claim, ['subjects' => $subjects]);
        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof ServicePaymentSubject) {
                $category = $this->catalogs->publishedCategory('services', 'services.dispute');
                if (null === $category) {
                    throw new \DomainException('Service dispute support is not currently available.');
                }
                $draft = $this->intake->start($actorId, 'services');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associatePayment($draft, $claim->subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->description);

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
        if (!$subject instanceof ServicePaymentSubject) {
            throw new AccessDeniedHttpException('We could not associate this payment with your account.');
        }

        $claim = new ServiceDisputeClaimData();
        $claim->subject = $subject;
        $form = $this->forms->create(ServiceDisputeClaimType::class, $claim, ['subjects' => [$subject]]);
        if ($request->isMethod('POST')) {
            $payload = $this->requestPayload($request);
            $payload['subject'] = hash('sha256', $subject->paymentReference);
            $form->submit($payload);
            if ($form->isValid()) {
                $category = $this->catalogs->publishedCategory('services', 'services.dispute');
                if (null === $category) {
                    throw new \DomainException('Service dispute support is not currently available.');
                }

                $draft = $this->intake->start($actorId, 'services');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associatePayment($draft, $subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->description);

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
        $claim = $this->claimFromDraft($draft, $subjects);
        $form = $this->forms->create(ServiceDisputeClaimType::class, $claim, ['subjects' => $subjects]);
        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof ServicePaymentSubject) {
                $this->disputes->associatePayment($draft, $claim->subject->paymentReference);
                $this->disputes->recordCustomerClaim($draft, $claim->description);

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
        if (!$draft instanceof CaseDraftEntity || 'services' !== $draft->getBusinessContext() || 'dispute' !== $draft->getCatalogCategory()?->getSlug()) {
            throw new AccessDeniedHttpException('We could not associate this case draft with your account.');
        }

        return $draft;
    }

    /** @param list<ServicePaymentSubject> $subjects */
    private function claimFromDraft(CaseDraftEntity $draft, array $subjects): ServiceDisputeClaimData
    {
        $claim = new ServiceDisputeClaimData();
        $stored = $draft->getContributionData()['paying.service_dispute_subject'] ?? [];
        $paymentReference = is_array($stored) ? (string) ($stored['paymentReference'] ?? '') : '';
        foreach ($subjects as $subject) {
            if ($subject->paymentReference === $paymentReference) {
                $claim->subject = $subject;
                break;
            }
        }
        $facts = $draft->getSuppliedFacts()['serviceDispute'] ?? [];
        $claim->description = is_array($facts) ? (string) ($facts['description'] ?? '') : '';

        return $claim;
    }

    /** @param list<ServicePaymentSubject> $subjects
     * @return array<string, mixed>
     */
    private function formPayload(ServiceDisputeClaimData $claim, array $subjects, ?string $draftReference): array
    {
        $options = array_map(static fn (ServicePaymentSubject $subject): array => [
            'label' => sprintf('%s · %s %s · %s', $subject->orderNumber, $subject->amount, $subject->currency, $subject->status),
            'value' => hash('sha256', $subject->paymentReference),
        ], $subjects);

        return [
            '_view' => $this->view(null === $draftReference ? 'create' : 'edit', 'form'),
            'interface' => $this->content('Service dispute', 'Select a payment from one of your orders and describe the dispute.'),
            'data' => [
                'draftReference' => $draftReference,
                'action' => null === $draftReference ? '/support/service/dispute' : sprintf('/support/service/dispute/%s/edit', $draftReference),
                'method' => 'POST',
                'formFields' => [
                    ['nameEntity' => 'subject', 'label' => 'Payment', 'type' => 'select', 'value' => $claim->subject instanceof ServicePaymentSubject ? hash('sha256', $claim->subject->paymentReference) : null, 'required' => true, 'options' => $options],
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

        return array_intersect_key($request->request->all(), array_flip(['subject', 'description']));
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
