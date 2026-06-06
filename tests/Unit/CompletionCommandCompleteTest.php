<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Cli\Command\CompletionCommand;
use Switon\Cli\RouterInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\MakerInterface;

#[AllowMockObjectsWithoutExpectations]
class CompletionCommandCompleteTest extends TestCase
{
    public function testCompleteActionSuggestsCommandEntriesAtPositionOne(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['1', 'switon', '']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn([
            'list' => DummyCompletionMultiActionCommand::class,
            'hidden' => DummyCompletionHiddenCommand::class,
        ]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturnCallback(static fn (string $class): bool => $class === DummyCompletionHiddenCommand::class);
        $inspector->method('getActions')->willReturnCallback(static function (string $class): array {
            if ($class === DummyCompletionMultiActionCommand::class) {
                return ['default' => 'd', 'ping' => 'p'];
            }
            return [];
        });

        $cmd = new TestableCompletionCommand2();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $router,
        );

        $code = $cmd->completeAction();

        $this->assertSame(0, $code);
        $this->assertNotEmpty($written);
        $last = $written[array_key_last($written)];
        $this->assertStringContainsString('list', $last);
        $this->assertStringNotContainsString('hidden', $last);
        $this->assertStringContainsString('list:ping', $last);
    }

    public function testCompleteActionCompletesInlineActionAfterColon(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['1', 'switon', 'list:pi']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['list' => DummyCompletionMultiActionCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd', 'ping' => 'p', 'pilot' => 'x']);

        $cmd = new TestableCompletionCommand2();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $router,
        );

        $code = $cmd->completeAction();
        $this->assertSame(0, $code);

        $last = $written[array_key_last($written)];
        // Must expand to list:ping + list:pilot (filtered by "pi").
        $this->assertStringContainsString('list:ping', $last);
        $this->assertStringContainsString('list:pilot', $last);
    }

    public function testCompleteActionSuggestsArgumentValuesFromDefaultBoolType(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        // position=3, args: [entry, cmd, action, prevArg, currentValue]
        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['4', 'switon', 'demo', 'default', '--dry-run', '']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionBoolCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd']);

        $cmd = new TestableCompletionCommand2();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $router,
        );

        $code = $cmd->completeAction();
        $this->assertSame(0, $code);

        $last = $written[array_key_last($written)];
        $this->assertStringContainsString('true', $last);
        $this->assertStringContainsString('false', $last);
        $this->assertStringNotContainsString('--dry-run', $last);
    }
}

class TestableCompletionCommand2 extends CompletionCommand
{
    public function injectAll(
        ConsoleInterface          $console,
        CommandDiscoveryInterface $discovery,
        CommandInspectorInterface $inspector,
        FilesystemInterface       $filesystem,
        MakerInterface            $maker,
        RouterInterface           $router,
    ): void {
        $this->console = $console;
        $this->commandDiscovery = $discovery;
        $this->commandInspector = $inspector;
        $this->filesystem = $filesystem;
        $this->maker = $maker;
        $this->router = $router;
    }
}

class DummyCompletionHiddenCommand
{
}

class DummyCompletionMultiActionCommand
{
    public function defaultAction(): void
    {
    }

    public function pingAction(): void
    {
    }
}

class DummyCompletionBoolCommand
{
    public function defaultAction(bool $dry_run = false): void
    {
    }
}
