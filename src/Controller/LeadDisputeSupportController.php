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
        $reasons = $this->catalogs->publishedChildren('leads', 'leads.dispute');
        $claim = new LeadDisputeClaimData();
        $form = $this->forms->create(LeadDisputeClaimType::class, $claim, ['subjects' => $subjects, 'reasons' => $reasons]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof LeadSubject) {
                $category = $this->catalogs->publishedCategory('leads', $claim->reasonPath);
                if (null === $category || !str_starts_with($category->getPath(), 'leads.dispute.')) {
                    throw new \DomainException('The selected lead dispute reason is not available.');
                }

                $draft = $this->intake->start($actorId, 'leads');
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associateLead($draft, $claim->subject->leadReference);
                $this->disputes->recordCustomerClaim($draft, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, $reasons, null);
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
        $reasons = $this->catalogs->publishedChildren('leads', 'leads.dispute');
        $claim = $this->claimFromDraft($draft, $subjects);
        $form = $this->forms->create(LeadDisputeClaimType::class, $claim, ['subjects' => $subjects, 'reasons' => $reasons]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof LeadSubject) {
                $category = $this->catalogs->publishedCategory('leads', $claim->reasonPath);
                if (null === $category || !str_starts_with($category->getPath(), 'leads.dispute.')) {
                    throw new \DomainException('The selected lead dispute reason is not available.');
                }
                $this->intake->selectCategory($draft, $category);
                $this->disputes->associateLead($draft, $claim->subject->leadReference);
                $this->disputes->recordCustomerClaim($draft, $claim->description);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($claim, $subjects, $reasons, $draft->getDraftReference());
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
        if (!$draft instanceof CaseDraftEntity || 'leads' !== $draft->getBusinessContext() || !str_starts_with($path, 'leads.dispute.')) {
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
        $claim->reasonPath = $draft->getCatalogCategory()?->getPath() ?? '';
        $facts = $draft->getSuppliedFacts()['leadDispute'] ?? [];
        $claim->description = is_array($facts) ? (string) ($facts['description'] ?? '') : '';

        return $claim;
    }

    /**
     * @param list<LeadSubject>                                      $subjects
     * @param list<array{title: string, path: string, slug: string}> $reasons
     *
     * @return array<string, mixed>
     */
    private function formPayload(LeadDisputeClaimData $claim, array $subjects, array $reasons, ?string $draftReference): array
    {
        $subjectOptions = array_map(static fn (LeadSubject $subject): array => [
            'label' => sprintf('%s · %s · score %d', $subject->leadReference, $subject->status, $subject->score),
            'value' => hash('sha256', $subject->leadReference),
        ], $subjects);
        $reasonOptions = array_map(static fn (array $reason): array => ['label' => $reason['title'], 'value' => $reason['path']], $reasons);

        return [
            '_view' => $this->view(null === $draftReference ? 'create' : 'edit', 'form'),
            'interface' => $this->content('Lead dispute', 'Choose a lead associated with your account and a Cataloging-owned dispute reason.'),
            'data' => [
                'draftReference' => $draftReference,
                'action' => null === $draftReference ? '/support/lead/dispute' : sprintf('/support/lead/dispute/%s/edit', $draftReference),
                'method' => 'POST',
                'formFields' => [
                    ['nameEntity' => 'subject', 'label' => 'Lead', 'type' => 'select', 'value' => $claim->subject instanceof LeadSubject ? hash('sha256', $claim->subject->leadReference) : null, 'required' => true, 'options' => $subjectOptions],
                    ['nameEntity' => 'reasonPath', 'label' => 'Dispute reason', 'type' => 'select', 'value' => $claim->reasonPath, 'required' => true, 'options' => $reasonOptions],
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

        return array_intersect_key($request->request->all(), array_flip(['subject', 'reasonPath', 'description']));
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
