<?php

declare(strict_types=1);

namespace App\Casing\Entity;

use App\Casing\Repository\CaseOutboxMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CaseOutboxMessageRepository::class)]
#[ORM\Table(name: 'case_outbox_message')]
#[ORM\Index(name: 'idx_case_outbox_pending', columns: ['dispatched', 'available_at', 'id'])]
#[ORM\UniqueConstraint(name: 'uniq_case_outbox_slug', columns: ['slug'])]
final class CaseOutboxMessageEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'guid')]
    private string $slug;

    #[ORM\Column(name: 'aggregate_id', length: 64)]
    private string $aggregateId;

    #[ORM\Column(name: 'event_type', length: 128)]
    private string $eventType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $dispatched = false;

    #[ORM\Column(name: 'dispatched_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(name: 'available_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableAt = null;

    /** @param array<string, mixed> $payload */
    public function __construct(string $aggregateId, string $eventType, array $payload)
    {
        $this->slug = Uuid::v7()->toRfc4122();
        $this->aggregateId = $aggregateId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function isPending(): bool
    {
        return !$this->dispatched && (null === $this->availableAt || $this->availableAt <= new \DateTimeImmutable());
    }

    public function markDispatched(): void
    {
        $this->dispatched = true;
        $this->dispatchedAt = new \DateTimeImmutable();
    }
}
