<?php

declare(strict_types=1);

namespace App\Casing\Service\Outbox;

use App\Casing\Entity\CaseOutboxMessageEntity;
use App\Casing\Event\CaseOpenedEvent;
use App\Casing\Event\CaseResolvedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CaseOutboxProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function process(int $limit = 100): int
    {
        $repository = $this->entityManager->getRepository(CaseOutboxMessageEntity::class);
        $messages = array_filter(
            $repository->findBy([], ['id' => 'ASC'], $limit),
            static fn (mixed $message): bool => $message instanceof CaseOutboxMessageEntity && $message->isPending(),
        );
        $count = 0;

        foreach ($messages as $message) {
            $payload = $message->payload();
            $eventType = $message->getEventType();
            $event = match ($eventType) {
                CaseOpenedEvent::class => new CaseOpenedEvent(
                    (string) ($payload['caseReference'] ?? ''),
                    (string) ($payload['actorId'] ?? ''),
                    (string) ($payload['businessContext'] ?? ''),
                    (string) ($payload['categoryPath'] ?? ''),
                    (string) ($payload['occurredAt'] ?? ''),
                ),
                CaseResolvedEvent::class => new CaseResolvedEvent(
                    (string) ($payload['caseReference'] ?? ''),
                    (string) ($payload['actorId'] ?? ''),
                    (string) ($payload['businessContext'] ?? ''),
                    (string) ($payload['categoryPath'] ?? ''),
                    (string) ($payload['occurredAt'] ?? ''),
                ),
                default => null,
            };
            if (null === $event) {
                continue;
            }

            $this->dispatcher->dispatch($event, $eventType);
            $message->markDispatched();
            ++$count;
        }

        $this->entityManager->flush();

        return $count;
    }
}
