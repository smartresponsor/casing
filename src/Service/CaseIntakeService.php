<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Entity\CaseEntity;
use App\Casing\Event\Domain\CaseOpenedEvent;
use App\Casing\Repository\CaseDraftRepository;
use App\Casing\Repository\CaseRepository;
use App\Casing\Service\Outbox\CaseOutboxWriter;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use Doctrine\ORM\EntityManagerInterface;

final class CaseIntakeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CaseDraftRepository $drafts,
        private readonly CaseRepository $cases,
        private readonly CaseCatalogService $catalogs,
        private readonly CaseOutboxWriter $outbox,
    ) {
    }

    public function start(string $actorId, string $catalogCode, string $tenant = 'default'): CaseDraftEntity
    {
        $actorId = trim($actorId);
        $catalogCode = trim($catalogCode);
        if ('' === $actorId || '' === $catalogCode) {
            throw new \InvalidArgumentException('Actor and business context are required.');
        }
        if (!$this->catalogs->contextExists($catalogCode, $tenant)) {
            throw new \DomainException('The requested support context is not available.');
        }

        $draft = new CaseDraftEntity($actorId, $catalogCode);
        $this->drafts->save($draft);

        return $draft;
    }

    public function resume(string $draftReference, string $actorId): ?CaseDraftEntity
    {
        return $this->drafts->findActorDraft(trim($draftReference), trim($actorId));
    }

    public function selectCategory(CaseDraftEntity $draft, CatalogCategoryEntity $category): void
    {
        if ($category->getCatalog()->getCode() !== $draft->getBusinessContext()) {
            throw new \DomainException('The selected category does not belong to the draft business context.');
        }
        if (!$category->isPublished()) {
            throw new \DomainException('The selected category is not available for customer intake.');
        }

        $draft->setCatalogCategory($category);
        $draft->setCurrentStep('details');
        $this->drafts->save($draft);
    }

    /** @param array<string, mixed> $facts */
    public function replaceSuppliedFacts(CaseDraftEntity $draft, array $facts): void
    {
        $draft->setSuppliedFacts($facts);
        $this->drafts->save($draft);
    }

    /** @param array<string, mixed> $data */
    public function replaceContributionData(CaseDraftEntity $draft, string $component, array $data): void
    {
        $component = trim($component);
        if ('' === $component) {
            throw new \InvalidArgumentException('Contribution component is required.');
        }

        $contributions = $draft->getContributionData();
        $contributions[$component] = $data;
        $draft->setContributionData($contributions);
        $this->drafts->save($draft);
    }

    public function describe(CaseDraftEntity $draft, ?string $description): void
    {
        $draft->setDescription($description);
        $draft->setCurrentStep('review');
        $this->drafts->save($draft);
    }

    public function submit(string $draftReference, string $actorId): CaseEntity
    {
        return $this->entityManager->wrapInTransaction(function () use ($draftReference, $actorId): CaseEntity {
            $draft = $this->drafts->findActorDraft(trim($draftReference), trim($actorId));
            if (!$draft instanceof CaseDraftEntity) {
                throw new \DomainException('We could not associate this case draft with your account.');
            }

            $category = $draft->getCatalogCategory();
            if (!$category instanceof CatalogCategoryEntity || !$category->isPublished()) {
                throw new \DomainException('The case draft is not ready for submission.');
            }
            if ($category->getCatalog()->getCode() !== $draft->getBusinessContext()) {
                throw new \DomainException('The case draft contains an invalid category context.');
            }

            $case = new CaseEntity($draft, $category);
            $this->cases->save($case, false);
            $event = new CaseOpenedEvent(
                $case->getCaseReference(),
                $case->getActorId(),
                $case->getBusinessContext(),
                $case->getCategoryPath(),
                (new \DateTimeImmutable())->format(DATE_ATOM),
            );
            $this->outbox->store($case->getCaseReference(), CaseOpenedEvent::class, get_object_vars($event));
            $this->drafts->remove($draft, false);
            $this->entityManager->flush();

            return $case;
        });
    }
}
