<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Dto\ProductReturnClaimData;
use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Form\ProductReturnClaimType;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Service\CaseIntakeService;
use App\Casing\Service\ProductReturnIntakeService;
use App\Casing\Value\PurchasedProductSubject;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProductReturnSupportController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private PurchasedProductSubjectResolverInterface $subjects,
        private CaseCatalogService $catalogs,
        private CaseIntakeService $intake,
        private ProductReturnIntakeService $returns,
        private FormFactoryInterface $forms,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/product/return', name: 'casing_support_product_return', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function intake(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $subjects = $this->subjects->listForActor($actorId);
        $claim = new ProductReturnClaimData();
        $form = $this->forms->create(ProductReturnClaimType::class, $claim, ['subjects' => $subjects]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof PurchasedProductSubject) {
                $category = $this->catalogs->publishedCategory('products', 'products.return');
                if (null === $category) {
                    throw new \DomainException('Product return support is not currently available.');
                }

                $draft = $this->intake->start($actorId, 'products');
                $this->intake->selectCategory($draft, $category);
                $this->returns->associatePurchasedProduct($draft, $claim->subject->orderReference, $claim->subject->itemReference);
                $this->returns->recordCustomerClaim($draft, $claim->reason, $claim->quantity);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($form, $subjects, '/support/product/return');
    }

    /** @return array<string, mixed> */
    #[Route('/support/product/return/{draftReference}', name: 'casing_support_product_return_review', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function review(Request $request, string $draftReference): array
    {
        $draft = $this->requireProductReturnDraft($draftReference, $this->actors->requireActorId($request));

        return $this->reviewPayload($draft);
    }

    /** @return array<string, mixed> */
    #[Route('/support/product/return/{draftReference}/edit', name: 'casing_support_product_return_edit', methods: ['GET', 'POST'], defaults: ['_view_controlled' => true])]
    public function edit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $draft = $this->requireProductReturnDraft($draftReference, $actorId);
        $subjects = $this->subjects->listForActor($actorId);
        $claim = $this->claimFromDraft($draft, $subjects);
        $form = $this->forms->create(ProductReturnClaimType::class, $claim, ['subjects' => $subjects]);

        if ($request->isMethod('POST')) {
            $form->submit($this->requestPayload($request));
            if ($form->isValid() && $claim->subject instanceof PurchasedProductSubject) {
                $this->returns->associatePurchasedProduct($draft, $claim->subject->orderReference, $claim->subject->itemReference);
                $this->returns->recordCustomerClaim($draft, $claim->reason, $claim->quantity);

                return $this->reviewPayload($draft);
            }
        }

        return $this->formPayload($form, $subjects, sprintf('/support/product/return/%s/edit', $draft->getDraftReference()), $draft->getDraftReference());
    }

    /** @return array<string, mixed> */
    #[Route('/support/product/return/{draftReference}/submit', name: 'casing_support_product_return_submit', methods: ['POST'], defaults: ['_view_controlled' => true])]
    public function submit(Request $request, string $draftReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $this->requireProductReturnDraft($draftReference, $actorId);
        $case = $this->intake->submit($draftReference, $actorId);

        return [
            '_view' => $this->view('show', 'success'),
            'interface' => $this->content('Return request submitted', 'Your case has been created and is ready for processing.'),
            'data' => [
                'caseReference' => $case->getCaseReference(),
                'status' => $case->getStatus()->value,
                'businessContext' => $case->getBusinessContext(),
                'supportCategory' => $case->getCatalogCategory()->getSlug(),
                'headerActions' => [
                    ['label' => 'Support', 'href' => '/support', 'variant' => 'default', 'operation' => 'index', 'enabled' => true, 'visibility' => 'visible'],
                ],
            ],
            'meta' => ['title' => 'Return request submitted'],
        ];
    }

    private function requireProductReturnDraft(string $draftReference, string $actorId): CaseDraftEntity
    {
        $draft = $this->intake->resume($draftReference, $actorId);
        if (!$draft instanceof CaseDraftEntity || 'products' !== $draft->getBusinessContext() || 'return' !== $draft->getCatalogCategory()?->getSlug()) {
            throw new AccessDeniedHttpException('We could not associate this case draft with your account.');
        }

        return $draft;
    }

    /** @param list<PurchasedProductSubject> $subjects */
    private function claimFromDraft(CaseDraftEntity $draft, array $subjects): ProductReturnClaimData
    {
        $claim = new ProductReturnClaimData();
        $subjectData = $draft->getContributionData()['ordering.return_subject'] ?? [];
        $orderReference = is_array($subjectData) ? (string) ($subjectData['orderReference'] ?? '') : '';
        $itemReference = is_array($subjectData) ? (string) ($subjectData['itemReference'] ?? '') : '';
        foreach ($subjects as $subject) {
            if ($subject->orderReference === $orderReference && $subject->itemReference === $itemReference) {
                $claim->subject = $subject;
                break;
            }
        }

        $return = $draft->getSuppliedFacts()['return'] ?? [];
        if (is_array($return)) {
            $claim->reason = (string) ($return['reason'] ?? '');
            $claim->quantity = isset($return['quantity']) && is_numeric($return['quantity']) ? (int) $return['quantity'] : null;
        }

        return $claim;
    }

    /** @param list<PurchasedProductSubject> $subjects
     * @return array<string, mixed>
     */
    private function formPayload(FormInterface $form, array $subjects, string $action, ?string $draftReference = null): array
    {
        $claim = $form->getData();
        $claim = $claim instanceof ProductReturnClaimData ? $claim : new ProductReturnClaimData();
        $options = array_map(static fn (PurchasedProductSubject $subject): array => [
            'label' => sprintf('%s · %s · %s %s', $subject->orderNumber, $subject->itemReference, $subject->unitPrice, $subject->currency),
            'value' => self::subjectToken($subject),
        ], $subjects);
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return [
            '_view' => $this->view(null === $draftReference ? 'create' : 'edit', 'form'),
            'interface' => $this->content('Product return', 'Select the purchased item and explain the return request.'),
            'data' => [
                'draftReference' => $draftReference,
                'action' => $action,
                'method' => 'POST',
                'formFields' => [
                    ['nameEntity' => 'subject', 'label' => 'Purchased product', 'type' => 'select', 'value' => $claim->subject instanceof PurchasedProductSubject ? self::subjectToken($claim->subject) : null, 'placeholder' => 'Select a purchased product', 'helpText' => null, 'required' => true, 'validationState' => null, 'errorText' => null, 'options' => $options],
                    ['nameEntity' => 'reason', 'label' => 'Why do you want to return it?', 'type' => 'textarea', 'value' => $claim->reason, 'placeholder' => 'Describe the issue or reason for return', 'helpText' => null, 'required' => true, 'validationState' => null, 'errorText' => null, 'options' => []],
                    ['nameEntity' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'value' => $claim->quantity, 'placeholder' => null, 'helpText' => 'Leave empty to discuss the full purchased quantity.', 'required' => false, 'validationState' => null, 'errorText' => null, 'options' => []],
                ],
                'errors' => $errors,
                'headerActions' => null === $draftReference ? [] : [
                    ['label' => 'Review', 'href' => sprintf('/support/product/return/%s', $draftReference), 'variant' => 'default', 'operation' => 'show', 'enabled' => true, 'visibility' => 'visible'],
                ],
            ],
            'meta' => ['title' => 'Product return'],
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(CaseDraftEntity $draft): array
    {
        return [
            '_view' => $this->view('show', 'review'),
            'interface' => $this->content('Review return request', 'Review the verified purchase context and your supplied claim before submission.'),
            'data' => [
                'draftReference' => $draft->getDraftReference(),
                'businessContext' => $draft->getBusinessContext(),
                'supportCategory' => $draft->getCatalogCategory()?->getSlug(),
                'verifiedContext' => $draft->getContributionData()['ordering.return_subject'] ?? null,
                'suppliedFacts' => $draft->getSuppliedFacts()['return'] ?? null,
                'headerActions' => [
                    ['label' => 'Back', 'href' => sprintf('/support/product/return/%s/edit', $draft->getDraftReference()), 'variant' => 'default', 'operation' => 'edit', 'enabled' => true, 'visibility' => 'visible'],
                    ['label' => 'Submit', 'href' => sprintf('/support/product/return/%s/submit', $draft->getDraftReference()), 'variant' => 'primary', 'operation' => 'submit', 'method' => 'POST', 'enabled' => true, 'visibility' => 'visible'],
                ],
            ],
            'meta' => ['title' => 'Review return request'],
        ];
    }

    /** @return array<string, mixed> */
    private function requestPayload(Request $request): array
    {
        $payload = $request->request->all('product_return_claim');
        if ([] !== $payload) {
            return $payload;
        }

        $all = $request->request->all();

        return array_intersect_key($all, array_flip(['subject', 'reason', 'quantity']));
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

    private static function subjectToken(PurchasedProductSubject $subject): string
    {
        return hash('sha256', $subject->orderReference."\0".$subject->itemReference);
    }
}
