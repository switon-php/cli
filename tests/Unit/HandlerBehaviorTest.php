<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Cli\ErrorHandlerInterface;
use Switon\Cli\Event\CliInvoked;
use Switon\Cli\Event\CliInvoking;
use Switon\Cli\Event\ToolInvoked;
use Switon\Cli\Event\ToolInvoking;
use Switon\Cli\Exception\OptionsException;
use Switon\Cli\Handler;
use Switon\Cli\OptionsInterface;
use Switon\Cli\RouterInterface;
use Switon\Cli\ServerInterface;
use Switon\Command\Attribute\Tool;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Core\Attribute\Scene;
use Switon\Core\ConsoleInterface;
use Switon\Core\SceneManagerInterface;
use Switon\Invoking\InvokerInterface;
use Switon\Testing\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class HandlerBehaviorTest extends TestCase
{
    protected Handler $handler;

    protected EventDispatcherInterface&MockObject $dispatcher;
    protected SceneManagerInterface&MockObject $sceneManager;
    protected ConsoleInterface&MockObject $console;
    protected ContainerInterface&MockObject $psrContainer;
    protected OptionsInterface&MockObject $options;
    protected ArgumentsBinderInterface&MockObject $argumentsBinder;
    protected InvokerInterface&MockObject $invoker;
    protected CommandDiscoveryInterface&MockObject $discovery;
    protected RouterInterface&MockObject $router;
    protected ErrorHandlerInterface&MockObject $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new Handler();
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->sceneManager = $this->createMock(SceneManagerInterface::class);
        $this->console = $this->createMock(ConsoleInterface::class);
        $this->psrContainer = $this->createMock(ContainerInterface::class);
        $this->options = $this->createMock(OptionsInterface::class);
        $this->argumentsBinder = $this->createMock(ArgumentsBinderInterface::class);
        $this->invoker = $this->createMock(InvokerInterface::class);
        $this->discovery = $this->createMock(CommandDiscoveryInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->errorHandler = $this->createMock(ErrorHandlerInterface::class);

        $this->handler = $this->make(Handler::class, [
            'eventDispatcher' => $this->dispatcher,
            'sceneManager' => $this->sceneManager,
            'console' => $this->console,
            'container' => $this->psrContainer,
            'options' => $this->options,
            'argumentsBinder' => $this->argumentsBinder,
            'invoker' => $this->invoker,
            'commandDiscovery' => $this->discovery,
            'router' => $this->router,
            'errorHandler' => $this->errorHandler,
        ]);

        $this->options->method('normalize')->willReturn([]);
        $this->argumentsBinder->method('resolve')->willReturn([]);
    }

    public function testHandleDispatchesCliEventsAlwaysAndToolEventsOnlyForToolMethods(): void
    {
        $args = ['cli.php', 'dummy:run'];
        $this->prepareRoute('dummy', 'run', []);

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['dummy' => DummyToolCommand::class]);

        $instance = new DummyToolCommand();
        $this->psrContainer->expects($this->once())->method('get')->with(DummyToolCommand::class)->willReturn($instance);

        $this->invoker->expects($this->once())->method('invoke')->with([$instance, 'runAction'], [])->willReturn(null);

        $events = [];
        $this->dispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
        $this->assertCount(4, $events);
        $this->assertInstanceOf(CliInvoking::class, $events[0]);
        $this->assertInstanceOf(ToolInvoking::class, $events[1]);
        $this->assertInstanceOf(ToolInvoked::class, $events[2]);
        $this->assertInstanceOf(CliInvoked::class, $events[3]);
    }

    public function testHandleSkipsToolEventsWhenMethodIsNotMarkedAsTool(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['plain' => DummyPlainCommand::class]);

        $instance = new DummyPlainCommand();
        $this->psrContainer->expects($this->once())->method('get')->with(DummyPlainCommand::class)->willReturn($instance);
        $this->invoker->expects($this->once())->method('invoke')->with([$instance, 'runAction'], [])->willReturn(null);

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
        $this->assertCount(2, $events);
        $this->assertInstanceOf(CliInvoking::class, $events[0]);
        $this->assertInstanceOf(CliInvoked::class, $events[1]);
    }

    public function testHandleDoesNotDispatchToolEventsWhenContainerReturnsWrongInstanceType(): void
    {
        $args = ['cli.php', 'dummy:run'];
        $this->prepareRoute('dummy', 'run', []);

        // Registry says DummyToolCommand which has #[Tool] on runAction.
        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['dummy' => DummyToolCommand::class]);

        // But container returns a different instance without #[Tool] on runAction.
        $wrongInstance = new DummyPlainCommand();
        $this->psrContainer->expects($this->once())->method('get')->with(DummyToolCommand::class)->willReturn($wrongInstance);

        $this->invoker->expects($this->once())->method('invoke')->with([$wrongInstance, 'runAction'], [])->willReturn(null);

        $events = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
        $this->assertCount(2, $events);
        $this->assertInstanceOf(CliInvoking::class, $events[0]);
        $this->assertInstanceOf(CliInvoked::class, $events[1]);
    }

    public function testHandleReturnsInvokerIntAsExitCode(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $instance = new DummyPlainCommand();
        $this->psrContainer->method('get')->willReturn($instance);
        $this->invoker->method('invoke')->willReturn(12);
        $this->dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);

        $code = $this->handler->handle($args);

        $this->assertSame(12, $code);
    }

    public function testHandleMapsNonIntNonNullReturnToConsoleError(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $instance = new DummyPlainCommand();
        $this->psrContainer->method('get')->willReturn($instance);
        $this->invoker->method('invoke')->willReturn('bad');
        $this->dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);

        $this->console->expects($this->once())
            ->method('error')
            ->with('bad')
            ->willReturn(99);

        $code = $this->handler->handle($args);

        $this->assertSame(99, $code);
    }

    public function testHandleMapsThrowableToExecutionFailedAndReportsToErrorHandler(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $instance = new DummyPlainCommand();
        $this->psrContainer->method('get')->willReturn($instance);
        $this->dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);

        $ex = new RuntimeException('boom');
        $this->invoker->expects($this->once())->method('invoke')->willThrowException($ex);
        $this->errorHandler->expects($this->once())->method('handle')->with($ex);

        $code = $this->handler->handle($args);

        $this->assertSame(ServerInterface::EXIT_COMMAND_EXECUTION_FAILED, $code);
    }

    public function testHandleRethrowsOptionsException(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $instance = new DummyPlainCommand();
        $this->psrContainer->method('get')->willReturn($instance);
        $this->dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);

        $ex = OptionsException::of('bad opt');
        $this->invoker->expects($this->once())->method('invoke')->willThrowException($ex);
        $this->errorHandler->expects($this->never())->method('handle');

        $this->expectException(OptionsException::class);
        $this->handler->handle($args);
    }

    public function testHandleRethrowsOptionsExceptionFromArgumentsBinder(): void
    {
        $args = ['cli.php', 'plain:run'];
        $this->prepareRoute('plain', 'run', []);

        $this->discovery->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $this->psrContainer->method('get')->willReturn(new DummyPlainCommand());
        $this->dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);

        $exception = OptionsException::of('bad args');
        $this->argumentsBinder->expects($this->once())->method('resolve')->willThrowException($exception);
        $this->invoker->expects($this->never())->method('invoke');
        $this->errorHandler->expects($this->never())->method('handle');

        $this->expectException(OptionsException::class);
        $this->handler->handle($args);
    }

    public function testHandleReturnsActionNotFoundForDefaultWhenNoDefaultAndMultipleActions(): void
    {
        $args = ['cli.php', 'nodefault'];
        $this->prepareRoute('nodefault', 'default', []);

        $this->discovery->expects($this->once())->method('discover')->willReturn(['nodefault' => DummyNoDefaultCommand::class]);
        $this->psrContainer->expects($this->once())->method('get')->with(DummyNoDefaultCommand::class)->willReturn(new DummyNoDefaultCommand());

        $this->console->expects($this->once())
            ->method('error')
            ->with('"{cmd}" action does not exist', ['cmd' => 'nodefault:default'], ServerInterface::EXIT_ACTION_NOT_FOUND)
            ->willReturn(ServerInterface::EXIT_ACTION_NOT_FOUND);

        $code = $this->handler->handle($args);

        $this->assertSame(ServerInterface::EXIT_ACTION_NOT_FOUND, $code);
    }

    public function testHandleRequiresDefaultActionWhenNoActionIsGiven(): void
    {
        $args = ['cli.php', 'single'];
        $this->prepareRoute('single', 'default', []);

        $this->discovery->expects($this->once())->method('discover')->willReturn(['single' => DummySingleActionNoDefaultCommand::class]);
        $this->psrContainer->expects($this->once())
            ->method('get')
            ->with(DummySingleActionNoDefaultCommand::class)
            ->willReturn(new DummySingleActionNoDefaultCommand());
        $this->console->expects($this->once())
            ->method('error')
            ->with('"{cmd}" action does not exist', ['cmd' => 'single:default'], ServerInterface::EXIT_ACTION_NOT_FOUND)
            ->willReturn(ServerInterface::EXIT_ACTION_NOT_FOUND);

        $code = $this->handler->handle($args);

        $this->assertSame(ServerInterface::EXIT_ACTION_NOT_FOUND, $code);
    }

    public function testHandleKeepsCommandNameShapeAndKebabizesActionInErrorMessage(): void
    {
        $args = ['cli.php', 'foo-bar:bazQux'];
        $this->prepareRoute('foo-bar', 'bazQux', []);

        $this->discovery->expects($this->once())->method('discover')->willReturn([]);
        $this->console->expects($this->once())
            ->method('error')
            ->with('"{cmd}" command does not exist', ['cmd' => 'foo-bar:baz-qux'], ServerInterface::EXIT_COMMAND_NOT_FOUND)
            ->willReturn(ServerInterface::EXIT_COMMAND_NOT_FOUND);

        $code = $this->handler->handle($args);
        $this->assertSame(ServerInterface::EXIT_COMMAND_NOT_FOUND, $code);
    }

    public function testHandleFormatsMissingActionInKebabCaseForErrorMessage(): void
    {
        $args = ['cli.php', 'plain:doThing'];
        $this->prepareRoute('plain', 'doThing', []);

        $this->discovery->expects($this->once())->method('discover')->willReturn(['plain' => DummyPlainCommand::class]);
        $this->psrContainer->expects($this->once())->method('get')->with(DummyPlainCommand::class)->willReturn(new DummyPlainCommand());

        $this->console->expects($this->once())
            ->method('error')
            ->with('"{cmd}" action does not exist', ['cmd' => 'plain:do-thing'], ServerInterface::EXIT_ACTION_NOT_FOUND)
            ->willReturn(ServerInterface::EXIT_ACTION_NOT_FOUND);

        $code = $this->handler->handle($args);
        $this->assertSame(ServerInterface::EXIT_ACTION_NOT_FOUND, $code);
    }

    public function testHandleUsesKebabCaseCommandNameDirectlyForDiscoveryLookup(): void
    {
        $args = ['cli.php', 'backup-database:run-task'];
        $this->prepareRoute('backup-database', 'run-task', []);

        $instance = new DummyMultiWordCommand();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['backup-database' => DummyMultiWordCommand::class]);
        $this->psrContainer->expects($this->once())->method('get')->with(DummyMultiWordCommand::class)->willReturn($instance);
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $event): object => $event);
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->with([$instance, 'runTaskAction'], [])
            ->willReturn(0);

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
    }

    public function testHandleAppliesCommandSceneWhenAttributeExists(): void
    {
        $args = ['cli.php', 'schedule:run'];
        $this->prepareRoute('schedule', 'run', []);

        $instance = new DummyScheduleCommand();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['schedule' => DummyScheduleCommand::class]);
        $this->sceneManager->expects($this->once())->method('setScene')->with('schedule');
        $this->psrContainer->expects($this->once())->method('get')->with(DummyScheduleCommand::class)->willReturn($instance);
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $event): object => $event);
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->with([$instance, 'runAction'], [])
            ->willReturn(0);

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
    }

    public function testHandleDoesNotSetSceneWhenSceneAttributeNameIsEmpty(): void
    {
        $args = ['cli.php', 'emptyscene:run'];
        $this->prepareRoute('emptyscene', 'run', []);

        $instance = new DummyEmptySceneCommand();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn(['emptyscene' => DummyEmptySceneCommand::class]);
        $this->sceneManager->expects($this->never())->method('setScene');
        $this->psrContainer->expects($this->once())->method('get')->with(DummyEmptySceneCommand::class)->willReturn($instance);
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $event): object => $event);
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->with([$instance, 'runAction'], [])
            ->willReturn(0);

        $code = $this->handler->handle($args);

        $this->assertSame(0, $code);
    }

    private function prepareRoute(string $command, string $action, array $params): void
    {
        $this->router->expects($this->once())->method('parse');
        $this->router->expects($this->once())->method('getParams')->willReturn($params);
        $this->options->expects($this->once())->method('parse')->with($params);
        $this->router->expects($this->once())->method('getCommand')->willReturn($command);
        $this->router->expects($this->once())->method('getAction')->willReturn($action);
    }

}

class DummyToolCommand
{
    #[Tool('dummy:run')]
    public function runAction(): void
    {
    }
}

class DummyPlainCommand
{
    public function runAction(): void
    {
    }
}

class DummyNoDefaultCommand
{
    public function aAction(): void
    {
    }

    public function bAction(): void
    {
    }
}

class DummySingleActionNoDefaultCommand
{
    public function onlyAction(): void
    {
    }
}

class DummyMultiWordCommand
{
    public function runTaskAction(): void
    {
    }
}

#[Scene('schedule')]
class DummyScheduleCommand
{
    public function runAction(): void
    {
    }
}

#[Scene('')]
class DummyEmptySceneCommand
{
    public function runAction(): void
    {
    }
}
