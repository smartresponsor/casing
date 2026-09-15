<?php

declare(strict_types=1);

namespace App\Casing\Entity;

use App\Casing\Repository\CaseDraftRepository;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CaseDraftRepository::class)]
#[ORM\Table(name: 'case_draft')]
#[ORM\Index(name: 'idx_case_draft_actor_context', columns: ['actor_id', 'business_context'])]
final class CaseDraftEntity
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'draft_reference', type: 'string', length: 26, unique: true)]
    private string $draftReference;

    #[ORM\Column(name: 'actor_id', type: 'string', length: 190)]
    private string $actorId;

    #[ORM\Column(name: 'business_context', type: 'string', length: 64)]
    private string $businessContext;

    #[ORM\ManyToOne(targetEntity: CatalogCategoryEntity::class)]
    #[ORM\JoinColumn(name: 'catalog_category_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?CatalogCategoryEntity $catalogCategory = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var array<int, array{component: string, type: string, id: string}> */
    #[ORM\Column(name: 'subject_references', type: 'json')]
    private array $subjectReferences = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'supplied_facts', type: 'json')]
    private array $suppliedFacts = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'contribution_data', type: 'json')]
    private array $contributionData = [];

    /** @var list<string> */
    #[ORM\Column(name: 'attachment_references', type: 'json')]
    private array $attachmentReferences = [];

    #[ORM\Column(name: 'current_step', type: 'string', length: 64)]
    private string $currentStep = 'context';

    public function __construct(string $actorId, string $businessContext)
    {
        $this->draftReference = (string) new Ulid();
        $this->actorId = trim($actorId);
        $this->businessContext = trim($businessContext);
        $this->initializeObjectIdentity(objectSlug: 'case-draft-'.$this->draftReference);
        $this->initializeObjectAudit();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDraftReference(): string
    {
        return $this->draftReference;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getBusinessContext(): string
    {
        return $this->businessContext;
    }

    public function getCatalogCategory(): ?CatalogCategoryEntity
    {
        return $this->catalogCategory;
    }

    public function setCatalogCategory(?CatalogCategoryEntity $catalogCategory): void
    {
        $this->catalogCategory = $catalogCategory;
        $this->touchModified();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = null === $description ? null : trim($description);
        $this->touchModified();
    }

    public function getSubjectReferences(): array
    {
        return $this->subjectReferences;
    }

    public function setSubjectReferences(array $subjectReferences): void
    {
        $this->subjectReferences = $subjectReferences;
        $this->touchModified();
    }

    public function getSuppliedFacts(): array
    {
        return $this->suppliedFacts;
    }

    public function setSuppliedFacts(array $suppliedFacts): void
    {
        $this->suppliedFacts = $suppliedFacts;
        $this->touchModified();
    }

    public function getContributionData(): array
    {
        return $this->contributionData;
    }

    public function setContributionData(array $contributionData): void
    {
        $this->contributionData = $contributionData;
        $this->touchModified();
    }

    public function getAttachmentReferences(): array
    {
        return $this->attachmentReferences;
    }

    public function setAttachmentReferences(array $attachmentReferences): void
    {
        $this->attachmentReferences = array_values($attachmentReferences);
        $this->touchModified();
    }

    public function getCurrentStep(): string
    {
        return $this->currentStep;
    }

    public function setCurrentStep(string $currentStep): void
    {
        $this->currentStep = trim($currentStep);
        $this->touchModified();
    }
}
