<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Command\CaseOutboxProcessCommand;
use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;
use App\Casing\Service\Outbox\CaseOutboxProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CaseOutboxProcessCommandTest extends TestCase
{
    public function testCommandDispatchesPendingOutboxAndReportsCount(): void
    {
        $messages = $this->createMock(CaseOutboxMessageRepositoryInterface::class);
        $messages->expects(self::once())->method('findDispatchable')->with(100)->willReturn([]);
        $messages->expects(self::once())->method('flush');

        $processor = new CaseOutboxProcessor($messages, $this->createStub(EventDispatcherInterface::class));
        $tester = new CommandTester(new CaseOutboxProcessCommand($processor));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Dispatched 0 Casing outbox event(s).', $tester->getDisplay());
    }
}
