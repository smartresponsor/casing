<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Contract\LeadSubjectResolverInterface;
use App\Casing\Dto\LeadDisputeClaimData;
use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Form\LeadDisputeClaimType;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Service\CaseIntakeService;
use App\Casing\Service\LeadDisputeIntakeService;
use App\Casing\Value\LeadSubject;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class LeadDisputeSupportController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private LeadSubjectResolverInterface $subjects,
        private CaseCatalogService $catalogs,
        private CaseIntakeService $intake,
        private LeadDisputeIntakeService $disputes,
        private FormFactoryInterface $forms,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/lead/dispute', name: 'casing_support_lead_dispute', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function intake(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subjects = $this->subjects->listForActor($actorId);
        $types = $this->catalogs->publishedTypes('leads', 'leads.dispute');
        $claim = new LeadDisputeClaimData();
        $form = $this->forms->create(LeadDisputeClaimType::class, $claim, ['subjects' => $subjects, 'types' => $types]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof LeadSubject) {
                $category = $this->catalogs->publishedCategory('leads', 'leads.dispute');
                if (null === $category || !$this->catalogs->isPublishedType('leads', 'leads.dispute', $claim->typeCode)) {
                    throw new \DomainException('The selected lead dispute reason is not available.');
                }

                $draft = $this->intake->start($actorId, 'leads');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associateLead($draft, $claim->subject->leadReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, $types, null);
    }

    /** @return array<string, mixed> */
    #[Route('/support/lead/dispute/lead/{leadReference}', name: 'casing_support_lead_dispute_context', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function contextual(Request $request, string $leadReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subject = $this->subjects->resolve($actorId, $leadReference);
        if (!$subject instanceof LeadSubject) {
            throw new AccessDeniedHttpException('We could not associate this lead with your account.');
        }

        $types = $this->catalogs->publishedTypes('leads', 'leads.dispute');
        $claim = new LeadDisputeClaimData();
        $claim->subject = $subject;
        $form = $this->forms->create(LeadDisputeClaimType::class, $claim, ['subjects' => [$subject], 'types' => $types]);
        if ($request->isMethod('POST')) {
            $payload = $this->requestPayload($request);
            $payload['subject'] = hash('sha256', $subject->leadReference);
            $form->submit($payload);
            if ($form->isValid()) {
                $category = $this->catalogs->publishedCategory('leads', 'leads.dispute');
                if (null === $category || !$this->catalogs->isPublishedType('leads', 'leads.dispute', $claim->typeCode)) {
                    throw new \DomainException('The selected lead dispute reason is not available.');
                }

                $draft = $this->intake->start($actorId, 'leads');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associateLead($draft, $subject->leadReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        $payload = $this->formPayload($claim, [$subject], $types, null);
        $payload['data']['action'] = sprintf('/support/lead/dispute/lead/%s', rawurlencode($leadReference));
        $payload['data']['contextLocked'] = true;
        $payload['data']['verifiedContext'] = $subject->toArray();

        return $payload;
    }

    /** @return array<string, mixed> */
    #[Route('/support/lead/dispute/{draftReference}', name: 'casing_support_lead_dispute_review', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function review(Request $request, string $draftReference): array
    {
        return $this->reviewPayload($this->requireDraft($draftReference, $this->actors->requireActorId($request)));
    }

    /** @return array<string, mixed> */
    #[Route('/support/lead/dispute/{draftReference}/edit', name: 'casing_support_lead_dispute_edit', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function edit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $draft = $this->requireDraft($draftReference, $actorId);
        $subjects = $this->subjects->listForActor($actorId);
        $types = $this->catalogs->publishedTypes('leads', 'leads.dispute');
        $claim = $this->claimFromDraft($draft, $subjects);
        $form = $this->forms->create(LeadDisputeClaimType::class, $claim, ['subjects' => $subjects, 'types' => $types]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof LeadSubject) {
                $category = $this->catalogs->publishedCategory('leads', 'leads.dispute');
                if (null === $category || !$this->catalogs->isPublishedType('leads', 'leads.dispute', $claim->typeCode)) {
                    throw new \DomainException('The selected lead dispute reason is not available.');
                }
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associateLead($draft, $claim->subject->leadReference);
                $this->disputes->recordCustomerClaim($draft, $claim->typeCode, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, $types, $draft->getDraftReference());
    }

    /** @return array<string, mixed> */
    #[Route('/support/lead/dispute/{draftReference}/submit', name: 'casing_support_lead_dispute_submit', methods: ['POST'], defaults: ['_view_controlled' => true])]
    public function submit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $this->requireDraft($draftReference, $actorId);
        $case = $this->intake->submit($draftReference, $actorId);

        return [
            '_view' => $this->view('show', 'success'),
            'interface' => $this->content('Lead dispute submitted', 'Your case has been created and is ready for processing.'),
            'data' => ['caseReference' => $case->getCaseReference(), 'status' => $case->getStatus()->value],
            'meta' => ['title' => 'Lead dispute submitted'],
        ];
    }

    private function requireDraft(string $draftReference, string $actorId): CaseDraftEntity
    {
        $draft = $this->intake->resume($draftReference, $actorId);
        $path = $draft?->getCatalogCategory()?->getPath() ?? '';
        if (!$draft instanceof CaseDraftEntity || 'leads' !== $draft->getBusinessContext() || 'leads.dispute' !== $path) {
            throw new AccessDeniedHttpException('We could not associate this case draft with your account.');
        }

        return $draft;
    }

    /** @param list<LeadSubject> $subjects */
    private function claimFromDraft(CaseDraftEntity $draft, array $subjects): LeadDisputeClaimData
    {
        $claim = new LeadDisputeClaimData();
        $stored = $draft->getContributionData()['relating.lead_dispute_subject'] ?? [];
        $leadReference = is_array($stored) ? (string) ($stored['leadReference'] ?? '') : '';
        foreach ($subjects as $subject) {
            if ($subject->leadReference === $leadReference) {
                $claim->subject = $subject;
                break;
            }
        }
        $catalogType = $draft->getContributionData()['cataloging.support_type'] ?? [];
        $claim->typeCode = is_array($catalogType) ? (string) ($catalogType['typeCode'] ?? '') : '';
        $facts = $draft->getSuppliedFacts()['leadDispute'] ?? [];
        $claim->description = is_array($facts) ? (string) ($facts['description'] ?? '') : '';

        return $claim;
    }

    /**
     * @param list<LeadSubject>                        $subjects
     * @param list<array{code: string, label: string}> $types
     *
     * @return array<string, mixed>
     */
    private function formPayload(LeadDisputeClaimData $claim, array $subjects, array $types, ?string $draftReference): array
    {
        $subjectOptions = array_map(static fn (LeadSubject $subject): array => [
            'label' => sprintf('%s · %s · score %d', $subject->leadReference, $subject->status, $subject->score),
            'value' => hash('sha256', $subject->leadReference),
        ], $subjects);
        $typeOptions = array_map(static fn (array $type): array => ['label' => $type['label'], 'value' => $type['code']], $types);

        return [
            '_view' => $this->view(null === $draftReference ? 'create' : 'edit', 'form'),
            'interface' => $this->content('Lead dispute', 'Choose a lead associated with your account and a Cataloging-owned dispute reason.'),
            'data' => [
                'draftReference' => $draftReference,
                'action' => null === $draftReference ? '/support/lead/dispute' : sprintf('/support/lead/dispute/%s/edit', $draftReference),
                'method' => 'POST',
                'formFields' => [
                    ['nameEntity' => 'subject', 'label' => 'Lead', 'type' => 'select', 'value' => $claim->subject instanceof LeadSubject ? hash('sha256', $claim->subject->leadReference) : null, 'required' => true, 'options' => $subjectOptions],
                    ['nameEntity' => 'typeCode', 'label' => 'Dispute reason', 'type' => 'select', 'value' => $claim->typeCode, 'required' => true, 'options' => $typeOptions],
                    ['nameEntity' => 'description', 'label' => 'Describe the issue', 'type' => 'textarea', 'value' => $claim->description, 'required' => true, 'options' => []],
                ],
            ],
            'meta' => ['title' => 'Lead dispute'],
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(CaseDraftEntity $draft): array
    {
        return [
            '_view' => $this->view('show', 'review'),
            'interface' => $this->content('Review lead dispute', 'Verified lead context is shown separately from your statement.'),
            'data' => [
                'draftReference' => $draft->getDraftReference(),
                'supportCategory' => $draft->getCatalogCategory()?->getPath(),
                'supportType' => $draft->getContributionData()['cataloging.support_type'] ?? null,
                'verifiedContext' => $draft->getContributionData()['relating.lead_dispute_subject'] ?? null,
                'suppliedFacts' => $draft->getSuppliedFacts()['leadDispute'] ?? null,
                'headerActions' => [
                    ['label' => 'Back', 'href' => sprintf('/support/lead/dispute/%s/edit', $draft->getDraftReference()), 'variant' => 'default', 'operation' => 'edit', 'enabled' => true, 'visibility' => 'visible'],
                    ['label' => 'Submit', 'href' => sprintf('/support/lead/dispute/%s/submit', $draft->getDraftReference()), 'variant' => 'primary', 'operation' => 'submit', 'method' => 'POST', 'enabled' => true, 'visibility' => 'visible'],
                ],
            ],
            'meta' => ['title' => 'Review lead dispute'],
        ];
    }

    /** @return array<string, mixed> */
    private function requestPayload(Request $request): array
    {
        $payload = $request->request->all('lead_dispute_claim');
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
