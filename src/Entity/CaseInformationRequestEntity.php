<?php

declare(strict_types=1);

namespace App\Casing\Entity;

use App\Casing\Repository\CaseInformationRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CaseInformationRequestRepository::class)]
#[ORM\Table(name: 'case_information_request')]
#[ORM\Index(name: 'idx_case_information_request_case_answered', columns: ['case_id', 'answered_at'])]
final class CaseInformationRequestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CaseEntity::class)]
    #[ORM\JoinColumn(name: 'case_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private CaseEntity $case;

    #[ORM\Column(type: 'text')]
    private string $question;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $answer = null;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'answered_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    public function __construct(CaseEntity $case, string $question)
    {
        $question = trim($question);
        if ('' === $question) {
            throw new \InvalidArgumentException('Information request question is required.');
        }

        $this->case = $case;
        $this->question = $question;
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCase(): CaseEntity
    {
        return $this->case;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function answer(string $answer): void
    {
        if (null !== $this->answeredAt) {
            throw new \DomainException('Information request has already been answered.');
        }

        $answer = trim($answer);
        if ('' === $answer) {
            throw new \InvalidArgumentException('Information request answer is required.');
        }

        $this->answer = $answer;
        $this->answeredAt = new \DateTimeImmutable();
    }
}
