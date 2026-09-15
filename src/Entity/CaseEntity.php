<?php

declare(strict_types=1);

namespace App\Casing\Entity;

use App\Casing\Enum\CaseStatus;
use App\Casing\Repository\CaseRepository;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CaseRepository::class)]
#[ORM\Table(name: 'case_record')]
#[ORM\Index(name: 'idx_case_actor_status', columns: ['actor_id', 'case_status'])]
final class CaseEntity
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectStateEmbeddableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'case_reference', type: 'string', length: 26, unique: true)]
    private string $caseReference;

    #[ORM\Column(name: 'source_draft_reference', type: 'string', length: 26, nullable: true)]
    private ?string $sourceDraftReference;

    #[ORM\Column(name: 'actor_id', type: 'string', length: 190)]
    private string $actorId;

    #[ORM\Column(name: 'business_context', type: 'string', length: 64)]
    private string $businessContext;

    #[ORM\ManyToOne(targetEntity: CatalogCategoryEntity::class)]
    #[ORM\JoinColumn(name: 'catalog_category_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private CatalogCategoryEntity $catalogCategory;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    /** @var array<int, array{component: string, type: string, id: string}> */
    #[ORM\Column(name: 'subject_references', type: 'json')]
    private array $subjectReferences;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'supplied_facts', type: 'json')]
    private array $suppliedFacts;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'contribution_data', type: 'json')]
    private array $contributionData;

    /** @var list<string> */
    #[ORM\Column(name: 'attachment_references', type: 'json')]
    private array $attachmentReferences;

    #[ORM\Column(name: 'case_status', type: 'string', length: 32, enumType: CaseStatus::class)]
    private CaseStatus $status = CaseStatus::Submitted;

    public function __construct(CaseDraftEntity $draft, CatalogCategoryEntity $catalogCategory)
    {
        $this->caseReference = (string) new Ulid();
        $this->sourceDraftReference = $draft->getDraftReference();
        $this->actorId = $draft->getActorId();
        $this->businessContext = $draft->getBusinessContext();
        $this->catalogCategory = $catalogCategory;
        $this->description = $draft->getDescription();
        $this->subjectReferences = $draft->getSubjectReferences();
        $this->suppliedFacts = $draft->getSuppliedFacts();
        $this->contributionData = $draft->getContributionData();
        $this->attachmentReferences = $draft->getAttachmentReferences();
        $this->initializeObjectIdentity(objectSlug: 'case-'.$this->caseReference);
        $this->initializeObjectAudit();
        $this->initializeObjectState(objectStatus: $this->status->value);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCaseReference(): string
    {
        return $this->caseReference;
    }

    public function getSourceDraftReference(): ?string
    {
        return $this->sourceDraftReference;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getBusinessContext(): string
    {
        return $this->businessContext;
    }

    public function getCatalogCategory(): CatalogCategoryEntity
    {
        return $this->catalogCategory;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCategoryPath(): string
    {
        return $this->catalogCategory->getPath();
    }

    public function getSubjectReferences(): array
    {
        return $this->subjectReferences;
    }

    public function getSuppliedFacts(): array
    {
        return $this->suppliedFacts;
    }

    public function appendFollowUp(string $message): void
    {
        $followUps = $this->suppliedFacts['followUp'] ?? [];
        if (!is_array($followUps)) {
            $followUps = [];
        }
        $followUps[] = ['message' => $message];
        $this->suppliedFacts['followUp'] = $followUps;
        $this->touchModified();
    }

    public function getContributionData(): array
    {
        return $this->contributionData;
    }

    public function getAttachmentReferences(): array
    {
        return $this->attachmentReferences;
    }

    public function getStatus(): CaseStatus
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    public function transitionTo(CaseStatus $status): void
    {
        $this->status = $status;
        $this->setObjectStatus($status->value);
        $this->touchModified();
    }

    public function canTransitionTo(CaseStatus $target): bool
    {
        return in_array($target, match ($this->status) {
            CaseStatus::Submitted => [CaseStatus::Processing, CaseStatus::Closed],
            CaseStatus::Processing => [CaseStatus::NeedsInformation, CaseStatus::Resolved, CaseStatus::Closed],
            CaseStatus::NeedsInformation => [CaseStatus::Processing, CaseStatus::Resolved, CaseStatus::Closed],
            CaseStatus::Resolved => [CaseStatus::Closed, CaseStatus::Processing],
            CaseStatus::Closed => [],
        }, true);
    }

    public function transitionToAllowed(CaseStatus $status): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new \DomainException(sprintf('Case cannot transition from %s to %s.', $this->status->value, $status->value));
        }

        $this->transitionTo($status);
    }
}
