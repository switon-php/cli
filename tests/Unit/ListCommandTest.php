<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Switon\Cli\Command\ListCommand;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Core\AppInterface;
use Switon\Core\ConsoleInterface;
use Switon\Kernel\VersionInterface;
use Switon\Testing\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ListCommandTest extends TestCase
{
    protected ListCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected AppInterface&MockObject $app;
    protected VersionInterface&MockObject $frameworkVersion;
    protected ContainerInterface&MockObject $psrContainer;
    protected CommandDiscoveryInterface&MockObject $commandDiscovery;
    protected CommandInspectorInterface&MockObject $commandInspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new ListCommand();
        $this->console = $this->createMock(ConsoleInterface::class);
        $this->app = $this->createMock(AppInterface::class);
        $this->frameworkVersion = $this->createMock(VersionInterface::class);
        $this->psrContainer = $this->createMock(ContainerInterface::class);
        $this->commandDiscovery = $this->createMock(CommandDiscoveryInterface::class);
        $this->commandInspector = $this->createMock(CommandInspectorInterface::class);

        $this->command = $this->make(ListCommand::class, [
            'console' => $this->console,
            'app' => $this->app,
            'frameworkVersion' => $this->frameworkVersion,
            'container' => $this->psrContainer,
            'commandDiscovery' => $this->commandDiscovery,
            'commandInspector' => $this->commandInspector,
        ]);

        $this->app->method('id')->willReturn('demo-app');
        $this->app->method('name')->willReturn('Demo App');
        $this->app->method('version')->willReturn('2.3.4');
        $this->app->method('env')->willReturn('dev');
        $this->app->method('isDebug')->willReturn(true);
        $this->frameworkVersion->method('version')->willReturn('3.4.2');
    }

    public function testDefaultActionJsonSkipsHiddenAndToolOnlyCommands(): void
    {
        $this->commandDiscovery->method('discover')->willReturn([
            'visible' => DummyVisibleCommand::class,
            'hidden' => DummyHiddenCommand::class,
            'tool-only' => DummyToolOnlyCommand::class,
            'missing' => 'Switon\\Cli\\Tests\\Unit\\MissingCommandClass',
        ]);

        $this->commandInspector->method('isHiddenCommand')
            ->willReturnCallback(static fn (string $class): bool => $class === DummyHiddenCommand::class);

        $this->commandInspector->method('getCommandDescription')
            ->willReturnCallback(static fn (string $class): string => $class === DummyVisibleCommand::class ? 'Visible command' : 'Other');

        $this->commandInspector->method('getActions')
            ->willReturnCallback(static function (string $class, bool $includeHidden): array {
                if ($class === DummyVisibleCommand::class) {
                    return ['default' => 'default action', 'runTask' => 'run task'];
                }
                if ($class === DummyToolOnlyCommand::class) {
                    return $includeHidden ? ['hiddenAction' => 'internal'] : [];
                }
                return [];
            });

        $this->commandInspector->method('getActionAiDoc')
            ->willReturnCallback(static function (string $class, string $action): ?string {
                if ($class === DummyVisibleCommand::class && $action === 'runTask') {
                    return 'tool doc';
                }
                return null;
            });

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            });

        $code = $this->command->defaultAction(false, true);

        $this->assertSame(0, $code);
        $this->assertSame('3.4.2', $captured['framework_version']);
        $this->assertSame('demo-app', $captured['app']['id']);
        $this->assertSame('Demo App', $captured['app']['name']);
        $this->assertSame('2.3.4', $captured['app']['version']);
        $this->assertCount(1, $captured['commands']);
        $this->assertSame('visible', $captured['commands'][0]['name']);
        $this->assertSame('builtin', $captured['commands'][0]['kind']);
        $this->assertSame('Visible command', $captured['commands'][0]['description']);
        $this->assertSame(
            [
                ['name' => 'default', 'invocation' => 'visible', 'description' => 'default action'],
                ['name' => 'run-task', 'invocation' => 'visible:run-task', 'description' => 'run task', 'ai_doc' => 'tool doc'],
            ],
            $captured['commands'][0]['actions']
        );
    }

    public function testDefaultActionJsonIncludesHiddenAndToolOnlyWhenAllEnabled(): void
    {
        $this->commandDiscovery->method('discover')->willReturn([
            'visible' => DummyVisibleCommand::class,
            'hidden' => DummyHiddenCommand::class,
            'tool-only' => DummyToolOnlyCommand::class,
        ]);

        $this->commandInspector->method('isHiddenCommand')
            ->willReturnCallback(static fn (string $class): bool => $class === DummyHiddenCommand::class);
        $this->commandInspector->method('getCommandDescription')->willReturn('desc');
        $this->commandInspector->method('getActions')
            ->willReturnCallback(static function (string $class, bool $includeHidden): array {
                if ($class === DummyToolOnlyCommand::class) {
                    return ['hiddenAction' => 'internal'];
                }
                return ['default' => 'ok'];
            });
        $this->commandInspector->method('getActionAiDoc')->willReturn(null);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            });

        $code = $this->command->defaultAction(true, true);

        $this->assertSame(0, $code);
        $names = array_column($captured['commands'], 'name');
        sort($names);
        $this->assertSame(['hidden', 'tool-only', 'visible'], $names);
    }

    public function testDefaultActionNonJsonRendersBuiltinAndApplicationTables(): void
    {
        if (!class_exists('App\\Console\\DemoCommand')) {
            class_alias(ListCommandAppFixture::class, 'App\\Console\\DemoCommand');
        }

        $this->console->method('colorize')->willReturnCallback(static fn (string $text): string => $text);

        $this->commandDiscovery->method('discover')->willReturn([
            'list' => ListCommandVisibleFixture::class,
            'missing' => 'Switon\\Cli\\Tests\\Unit\\NoSuchCommandClass',
            'app-cmd' => 'App\\Console\\DemoCommand',
        ]);

        $this->commandInspector->method('isHiddenCommand')->willReturn(false);
        $this->commandInspector->method('getCommandDescription')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    ListCommandVisibleFixture::class => 'Builtin list',
                    'App\\Console\\DemoCommand' => 'Application demo',
                    default => '',
                };
            });
        $this->commandInspector->method('getActions')
            ->willReturnCallback(static function (string $class): array {
                return match ($class) {
                    ListCommandVisibleFixture::class => ['default' => 'Show list'],
                    'App\\Console\\DemoCommand' => ['runTask' => 'Run task now'],
                    default => [],
                };
            });

        $lines = [];
        $this->console->method('writeLn')->willReturnCallback(static function (string $line = '') use (&$lines): void {
            $lines[] = $line;
        });

        $code = $this->command->defaultAction(false, false);

        $this->assertSame(0, $code);
        $output = implode("\n", $lines);
        $this->assertStringContainsString('Demo App 2.3.4 (framework: 3.4.2', $output);
        $this->assertStringContainsString(
            'Tip: run <command> --help for details, or list --all to include hidden commands.',
            $output
        );
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('Application commands:', $output);
        $this->assertStringContainsString('list', $output);
        $this->assertStringContainsString('app-cmd:run-task', $output);
        $this->assertStringContainsString('NoSuchCommandClass', $output);
    }

    public function testDefaultActionNonJsonAppOnlyUsesAvailableCommandsTitle(): void
    {
        if (!class_exists('App\\Console\\SoloAppCommand')) {
            class_alias(ListCommandSoloAppFixture::class, 'App\\Console\\SoloAppCommand');
        }

        $this->console->method('colorize')->willReturnCallback(static fn (string $text): string => $text);

        $this->commandDiscovery->method('discover')->willReturn([
            'solo' => 'App\\Console\\SoloAppCommand',
        ]);

        $this->commandInspector->method('isHiddenCommand')->willReturn(false);
        $this->commandInspector->method('getCommandDescription')->willReturn('Solo application command');
        $this->commandInspector->method('getActions')
            ->willReturn(['default' => 'Run solo']);

        $lines = [];
        $this->console->method('writeLn')->willReturnCallback(static function (string $line = '') use (&$lines): void {
            $lines[] = $line;
        });

        $code = $this->command->defaultAction(false, false);

        $this->assertSame(0, $code);
        $output = implode("\n", $lines);
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringNotContainsString('Application commands:', $output);
        $this->assertStringContainsString('solo', $output);
    }

    public function testDefaultActionRendersSingleRowWhenCommandHasNoSubcommands(): void
    {
        $this->console->method('colorize')->willReturnCallback(static fn (string $text): string => $text);

        $this->commandDiscovery->method('discover')->willReturn([
            'naked' => ListCommandNakedFixture::class,
        ]);

        $this->commandInspector->method('isHiddenCommand')->willReturn(false);
        $this->commandInspector->method('getCommandDescription')->willReturn('Root only');
        $this->commandInspector->method('getActions')->willReturn([]);

        $lines = [];
        $this->console->method('writeLn')->willReturnCallback(static function (string $line = '') use (&$lines): void {
            $lines[] = $line;
        });

        $code = $this->command->defaultAction(false, false);

        $this->assertSame(0, $code);
        $output = implode("\n", $lines);
        $this->assertStringContainsString('naked', $output);
        $this->assertStringContainsString('Root only', $output);
    }

}

class DummyVisibleCommand
{
}

class DummyHiddenCommand
{
}

class DummyToolOnlyCommand
{
}

class ListCommandVisibleFixture
{
    public function defaultAction(): void
    {
    }
}

class ListCommandAppFixture
{
    public function runTaskAction(): void
    {
    }
}

final class ListCommandSoloAppFixture
{
    public function defaultAction(): void
    {
    }
}

final class ListCommandNakedFixture
{
    public function defaultAction(): void
    {
    }
}
