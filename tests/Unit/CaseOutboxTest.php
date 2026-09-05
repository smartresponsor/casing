<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseOutboxMessageEntity;
use App\Casing\Event\CaseOpenedEvent;
use App\Casing\Service\Outbox\CaseOutboxProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CaseOutboxTest extends TestCase
{
    public function testOpenedEventIsReconstructedAndDispatched(): void
    {
        $message = new CaseOutboxMessageEntity(
            '01CASE',
            CaseOpenedEvent::class,
            [
                'caseReference' => '01CASE',
                'actorId' => 'accessing:user:42',
                'businessContext' => 'retailing.product',
                'categoryPath' => 'retailing.product.return',
                'occurredAt' => '2026-08-23T19:30:00-05:00',
            ],
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findBy')
            ->with([], ['id' => 'ASC'], 100)
            ->willReturn([$message]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(CaseOutboxMessageEntity::class)
            ->willReturn($repository);
        $entityManager->expects(self::once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (object $event): bool => $event instanceof CaseOpenedEvent
                    && '01CASE' === $event->caseReference
                    && 'accessing:user:42' === $event->actorId
                    && 'retailing.product' === $event->businessContext
                    && 'retailing.product.return' === $event->categoryPath),
                CaseOpenedEvent::class,
            )
            ->willReturnArgument(0);

        self::assertSame(1, (new CaseOutboxProcessor($entityManager, $dispatcher))->process());
        self::assertFalse($message->isPending());
    }
}
