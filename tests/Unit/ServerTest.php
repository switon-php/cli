<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Cli\ErrorHandlerInterface;
use Switon\Cli\Event\CommandExecuted;
use Switon\Cli\Event\CommandExecuting;
use Switon\Cli\Exception\OptionsException;
use Switon\Cli\HandlerInterface;
use Switon\Cli\Server;
use Switon\Cli\ServerInterface;
use Switon\Core\Runtime;
use Switon\Core\StopFlow;
use Switon\Testing\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class ServerTest extends TestCase
{
    protected TestableServer $server;
    protected EventDispatcherInterface&MockObject $dispatcher;
    protected ErrorHandlerInterface&MockObject $errorHandler;
    protected HandlerInterface&MockObject $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $this->handler = $this->createMock(HandlerInterface::class);
        $this->server = $this->make(TestableServer::class, [
            'eventDispatcher' => $this->dispatcher,
            'errorHandler' => $this->errorHandler,
            'handler' => $this->handler,
        ]);
    }

    public function testHandleDispatchesExecutingAndExecutedAndStoresExitCode(): void
    {
        $GLOBALS['argv'] = ['cli.php', 'list', '--json'];

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($GLOBALS['argv'])
            ->willReturn(7);

        $this->server->handle();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(CommandExecuting::class, $events[0]);
        $this->assertInstanceOf(CommandExecuted::class, $events[1]);
        $this->assertSame('cli.php', $events[0]->command);
        $this->assertSame('list --json', $events[0]->args);
        $this->assertSame('cli.php', $events[1]->command);
        $this->assertSame('list --json', $events[1]->args);
        $this->assertGreaterThanOrEqual(0.0, $events[1]->elapsed);
        $this->assertSame(7, $this->server->getExitCode());
    }

    public function testHandleMapsStopFlowToSuccessAndStillDispatchesExecuted(): void
    {
        $GLOBALS['argv'] = ['cli.php', 'list'];

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $this->handler->expects($this->once())->method('handle')->willThrowException(StopFlow::abort());
        $this->errorHandler->expects($this->never())->method('handle');

        $this->server->handle();

        $this->assertSame(ServerInterface::EXIT_SUCCESS, $this->server->getExitCode());
        $this->assertInstanceOf(CommandExecuting::class, $events[0]);
        $this->assertInstanceOf(CommandExecuted::class, $events[1]);
    }

    public function testHandleMapsOptionsExceptionToOptionsErrorAndReportsToErrorHandler(): void
    {
        $GLOBALS['argv'] = ['cli.php', 'list', '--bad'];

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $ex = OptionsException::of('invalid option');
        $this->handler->expects($this->once())->method('handle')->willThrowException($ex);
        $this->errorHandler->expects($this->once())->method('handle')->with($ex);

        $this->server->handle();

        $this->assertSame(ServerInterface::EXIT_OPTIONS_ERROR, $this->server->getExitCode());
        $this->assertInstanceOf(CommandExecuting::class, $events[0]);
        $this->assertInstanceOf(CommandExecuted::class, $events[1]);
    }

    public function testHandleMapsThrowableToSystemErrorAndReportsToErrorHandler(): void
    {
        $GLOBALS['argv'] = ['cli.php', 'list'];

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $ex = new RuntimeException('boom');
        $this->handler->expects($this->once())->method('handle')->willThrowException($ex);
        $this->errorHandler->expects($this->once())->method('handle')->with($ex);

        $this->server->handle();

        $this->assertSame(ServerInterface::EXIT_SYSTEM_ERROR, $this->server->getExitCode());
        $this->assertInstanceOf(CommandExecuting::class, $events[0]);
        $this->assertInstanceOf(CommandExecuted::class, $events[1]);
    }

    public function testStartInNonCoroutineModeRunsHandleAndTerminatesWithExitCode(): void
    {
        Runtime::setCoroutineEnabled(false);

        $server = new StartCapturingServer();
        $server->start();

        $this->assertTrue($server->handled);
        $this->assertSame(33, $server->terminatedWith);
    }

    public function testHandleUsesBasenameAndEmptyArgsWhenNoExtraArgv(): void
    {
        $GLOBALS['argv'] = ['/usr/local/bin/switon'];

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });
        $this->handler->expects($this->once())->method('handle')->with($GLOBALS['argv'])->willReturn(0);

        $this->server->handle();

        $this->assertInstanceOf(CommandExecuting::class, $events[0]);
        $this->assertSame('switon', $events[0]->command);
        $this->assertSame('', $events[0]->args);
        $this->assertSame(0, $this->server->getExitCode());
    }
}

class TestableServer extends Server
{
    public function getExitCode(): int
    {
        return $this->exit_code;
    }
}

class StartCapturingServer extends Server
{
    public bool $handled = false;
    public ?int $terminatedWith = null;

    public function handle(): void
    {
        $this->handled = true;
        $this->exit_code = 33;
    }

    protected function terminateProcess(int $code): void
    {
        $this->terminatedWith = $code;
        // no-exit for tests
    }
}
