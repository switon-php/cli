<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;
use Switon\Cli\ErrorHandler;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Switon\Testing\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class ErrorHandlerVerboseTest extends TestCase
{
    public function testHandleOutputsStackTraceWhenVerboseEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('boom', $this->arrayHasKey('exception'));

        $console = $this->createMock(ConsoleInterface::class);
        $console->method('colorize')->willReturnCallback(static fn (string $s): string => $s);
        $console->expects($this->atLeastOnce())->method('newLine');
        $console->expects($this->once())->method('block');
        $console->expects($this->atLeastOnce())->method('writeLn');

        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())->method('has')->with('verbose|v')->willReturn(true);

        $handler = $this->make(ErrorHandler::class, [
            'logger' => $logger,
            'console' => $console,
            'input' => $input,
        ]);

        $handler->handle(new RuntimeException('boom'));
        $this->addToAssertionCount(1);
    }

    public function testHandleIgnoresThrowableWhenVerboseCheckFails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error');

        $console = $this->createMock(ConsoleInterface::class);
        $console->method('newLine');
        $console->method('block');

        $input = $this->createMock(InputInterface::class);
        $input->method('has')->willThrowException(new RuntimeException('input failed'));

        $handler = $this->make(ErrorHandler::class, [
            'logger' => $logger,
            'console' => $console,
            'input' => $input,
        ]);

        // should not throw
        $handler->handle(new RuntimeException('boom'));
        $this->addToAssertionCount(1);
    }
}
