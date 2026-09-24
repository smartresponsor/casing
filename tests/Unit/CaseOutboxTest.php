<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseOutboxMessageEntity;
use App\Casing\Event\CaseOpenedEvent;
use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;
use App\Casing\Service\Outbox\CaseOutboxProcessor;
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

        $repository = $this->createMock(CaseOutboxMessageRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findDispatchable')
            ->with(100)
            ->willReturn([$message]);

        $repository->expects(self::once())->method('flush');

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

        self::assertSame(1, (new CaseOutboxProcessor($repository, $dispatcher))->process());
        self::assertFalse($message->isPending());
    }

    public function testUnsupportedEventTypeFailsObservablyAndRemainsPending(): void
    {
        $message = new CaseOutboxMessageEntity('01CASE', 'unsupported.event', []);

        $repository = $this->createMock(CaseOutboxMessageRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findDispatchable')
            ->with(100)
            ->willReturn([$message]);

        $repository->expects(self::never())->method('flush');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $processor = new CaseOutboxProcessor($repository, $dispatcher);

        try {
            $processor->process();
            self::fail('Unsupported event types must fail observably.');
        } catch (\UnexpectedValueException $exception) {
            self::assertStringContainsString('unsupported.event', $exception->getMessage());
        }

        self::assertTrue($message->isPending());
    }

    public function testResolvedEventIsReconstructedAndCustomLimitIsForwarded(): void
    {
        $message = new CaseOutboxMessageEntity(
            '01CASE',
            \App\Casing\Event\CaseResolvedEvent::class,
            [
                'caseReference' => '01CASE',
                'actorId' => 'actor-42',
                'businessContext' => 'retailing.service',
                'categoryPath' => 'retailing.service.dispute',
                'occurredAt' => '2026-09-24T10:00:00-05:00',
            ],
        );

        $repository = $this->createMock(CaseOutboxMessageRepositoryInterface::class);
        $repository->expects(self::once())->method('findDispatchable')->with(7)->willReturn([$message]);
        $repository->expects(self::once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (object $event): bool => $event instanceof \App\Casing\Event\CaseResolvedEvent
                    && '01CASE' === $event->caseReference
                    && 'actor-42' === $event->actorId
                    && 'retailing.service' === $event->businessContext
                    && 'retailing.service.dispute' === $event->categoryPath),
                \App\Casing\Event\CaseResolvedEvent::class,
            )
            ->willReturnArgument(0);

        self::assertSame(1, (new CaseOutboxProcessor($repository, $dispatcher))->process(7));
        self::assertFalse($message->isPending());
    }

    public function testEmptyBatchStillFlushesAndReturnsZero(): void
    {
        $repository = $this->createMock(CaseOutboxMessageRepositoryInterface::class);
        $repository->expects(self::once())->method('findDispatchable')->with(3)->willReturn([]);
        $repository->expects(self::once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        self::assertSame(0, (new CaseOutboxProcessor($repository, $dispatcher))->process(3));
    }
}
