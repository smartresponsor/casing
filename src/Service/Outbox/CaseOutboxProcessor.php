<?php

declare(strict_types=1);

namespace App\Casing\Service\Outbox;

use App\Casing\Event\CaseOpenedEvent;
use App\Casing\Event\CaseResolvedEvent;
use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CaseOutboxProcessor
{
    public function __construct(
        private CaseOutboxMessageRepositoryInterface $messages,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function process(int $limit = 100): int
    {
        $messages = $this->messages->findDispatchable($limit);
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
                default => throw new \UnexpectedValueException(sprintf('Unsupported Casing outbox event type "%s".', $eventType)),
            };

            $this->dispatcher->dispatch($event, $eventType);
            $message->markDispatched();
            ++$count;
        }

        $this->messages->flush();

        return $count;
    }
}
