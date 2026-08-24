<?php

declare(strict_types=1);

namespace App\Casing\Controller\Admin;

use App\Casing\Entity\CaseEntity;
use App\Casing\Enum\CaseStatus;
use App\Casing\Service\CaseInformationRequestService;
use App\Casing\Service\CaseLifecycleService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute(path: '', name: 'case')]
#[IsGranted('ROLE_ADMIN')]
final class CaseCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CaseInformationRequestService $informationRequests,
        private readonly CaseLifecycleService $lifecycle,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CaseEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Case')
            ->setEntityLabelInPlural('Case queue')
            ->setPageTitle(Crud::PAGE_INDEX, 'Case queue')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $processing = $this->transitionAction('processing', 'Start processing', CaseStatus::Processing, 'fa fa-play');
        $needsInformation = Action::new('needsInformation', 'Request information', 'fa fa-circle-question')
            ->linkToCrudAction('needsInformation')
            ->displayIf(static fn (CaseEntity $case): bool => $case->canTransitionTo(CaseStatus::NeedsInformation));
        $resolved = $this->transitionAction('resolved', 'Resolve', CaseStatus::Resolved, 'fa fa-check');
        $closed = $this->transitionAction('closed', 'Close', CaseStatus::Closed, 'fa fa-lock');
        $reopen = $this->transitionAction('reopen', 'Reopen', CaseStatus::Processing, 'fa fa-rotate-left');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $processing)
            ->add(Crud::PAGE_DETAIL, $processing)
            ->add(Crud::PAGE_INDEX, $needsInformation)
            ->add(Crud::PAGE_DETAIL, $needsInformation)
            ->add(Crud::PAGE_INDEX, $resolved)
            ->add(Crud::PAGE_DETAIL, $resolved)
            ->add(Crud::PAGE_INDEX, $closed)
            ->add(Crud::PAGE_DETAIL, $closed)
            ->add(Crud::PAGE_INDEX, $reopen)
            ->add(Crud::PAGE_DETAIL, $reopen);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('caseReference', 'Case');
        yield TextField::new('actorId', 'Actor');
        yield TextField::new('businessContext', 'Context');
        yield TextField::new('categoryPath', 'Category');
        yield TextField::new('statusValue', 'Status');
        yield ArrayField::new('subjectReferences')->hideOnIndex();
        yield ArrayField::new('suppliedFacts')->hideOnIndex();
        yield ArrayField::new('contributionData')->hideOnIndex();
        yield ArrayField::new('attachmentReferences')->hideOnIndex();
    }

    #[AdminRoute(path: '/{entityId}/processing', name: 'processing', options: ['methods' => ['POST']])]
    public function processing(AdminContext $context): Response
    {
        return $this->applyTransition($context, CaseStatus::Processing, 'Case moved to processing.');
    }

    #[AdminRoute(path: '/{entityId}/needs-information', name: 'needs_information', options: ['methods' => ['GET', 'POST']])]
    public function needsInformation(AdminContext $context, Request $request): Response
    {
        $entity = $context->getEntity()->getInstance();
        if (!$entity instanceof CaseEntity) {
            throw $this->createNotFoundException();
        }

        $form = $this->createFormBuilder()
            ->add('question', TextareaType::class, ['label' => 'Question for customer'])
            ->add('submit', SubmitType::class, ['label' => 'Request information'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->informationRequests->request($entity, (string) ($data['question'] ?? ''));
            $this->addFlash('success', 'Information request sent to customer.');

            return $this->redirect($context->getReferrer() ?? '/admin');
        }

        return $this->render('admin/case/request_information.html.twig', [
            'case' => $entity,
            'form' => $form,
        ]);
    }

    #[AdminRoute(path: '/{entityId}/resolved', name: 'resolved', options: ['methods' => ['POST']])]
    public function resolved(AdminContext $context): Response
    {
        return $this->applyTransition($context, CaseStatus::Resolved, 'Case resolved.');
    }

    #[AdminRoute(path: '/{entityId}/closed', name: 'closed', options: ['methods' => ['POST']])]
    public function closed(AdminContext $context): Response
    {
        return $this->applyTransition($context, CaseStatus::Closed, 'Case closed.');
    }

    #[AdminRoute(path: '/{entityId}/reopen', name: 'reopen', options: ['methods' => ['POST']])]
    public function reopen(AdminContext $context): Response
    {
        return $this->applyTransition($context, CaseStatus::Processing, 'Case reopened for processing.');
    }

    private function transitionAction(string $name, string $label, CaseStatus $target, string $icon): Action
    {
        return Action::new($name, $label, $icon)
            ->linkToCrudAction($name)
            ->renderAsForm()
            ->displayIf(static fn (CaseEntity $case): bool => $case->canTransitionTo($target));
    }

    private function applyTransition(AdminContext $context, CaseStatus $target, string $message): Response
    {
        $entity = $context->getEntity()->getInstance();
        if (!$entity instanceof CaseEntity) {
            throw $this->createNotFoundException();
        }

        $this->lifecycle->transition($entity, $target);
        $this->addFlash('success', $message);

        return $this->redirect($context->getReferrer() ?? '/admin');
    }
}
