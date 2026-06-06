<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Switon\Cli\Command\CommandHelpRenderer;
use Switon\Cli\OptionsInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Core\ConsoleInterface;
use ReflectionMethod;

#[AllowMockObjectsWithoutExpectations]
class CommandHelpRendererBehaviorTest extends TestCase
{
    public function testRenderRendersAllActionsAndOptionsTableWhenHelpMethodMissing(): void
    {
        $lines = [];
        $console = $this->createMock(ConsoleInterface::class);
        $console->method('colorize')->willReturnCallback(static fn (string $s): string => $s);
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $console->method('error')->willReturn(1);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new HelpRendererFixtureCommand());

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['fixture' => HelpRendererFixtureCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('getCommandDescription')->willReturn('Fixture command description');
        $inspector->method('getMethodDescription')->willReturnCallback(static function (ReflectionMethod $m): string {
            return match ($m->getName()) {
                'defaultAction' => 'Run default',
                'secondAction' => 'Run second',
                default => '',
            };
        });
        $inspector->method('hasOptions')->willReturn(true);

        $options = $this->createMock(OptionsInterface::class);
        $options->method('normalize')->willReturn(['foo' => 'f', 'bar' => 'b']);

        $renderer = new CommandHelpRenderer($console, $container, $discovery, $inspector, $options);

        $code = $renderer->render('fixture');

        $this->assertSame(0, $code);
        $out = implode("\n", $lines);
        $this->assertStringContainsString('fixture', $out);
        $this->assertStringContainsString('Fixture command description', $out);
        $this->assertStringContainsString('fixture:default', $out);
        $this->assertStringContainsString('fixture:second', $out);
        // Options table header should be printed at least once.
        $this->assertStringContainsString('Option', $out);
        $this->assertStringContainsString('Description', $out);
        $this->assertStringContainsString('Default', $out);
        // defaultAction has a help method, so renderer prints options for the other action.
        $this->assertStringContainsString('--name', $out);
    }

    public function testRenderCallsActionHelpMethodWhenPresent(): void
    {
        $console = $this->createMock(ConsoleInterface::class);
        $console->method('colorize')->willReturnCallback(static fn (string $s): string => $s);
        $console->method('writeLn');
        $console->method('error')->willReturn(1);

        $fixture = new HelpRendererFixtureCommand();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with(HelpRendererFixtureCommand::class)->willReturn($fixture);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['fixture' => HelpRendererFixtureCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('getCommandDescription')->willReturn('');
        $inspector->method('getMethodDescription')->willReturn('');
        $inspector->method('hasOptions')->willReturn(false);

        $options = $this->createMock(OptionsInterface::class);
        $options->method('normalize')->willReturn([]);

        $renderer = new CommandHelpRenderer($console, $container, $discovery, $inspector, $options);

        $code = $renderer->render('fixture');

        $this->assertSame(0, $code);
        $this->assertTrue($fixture->defaultHelpCalled);
    }

    public function testRenderKeepsKebabCaseCommandNamesAndKebabizesActions(): void
    {
        $lines = [];
        $console = $this->createMock(ConsoleInterface::class);
        $console->method('colorize')->willReturnCallback(static fn (string $s): string => $s);
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $console->method('error')->willReturn(1);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new HelpRendererMultiWordFixtureCommand());

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['backup-database' => HelpRendererMultiWordFixtureCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('getCommandDescription')->willReturn('Backup database');
        $inspector->method('getMethodDescription')->willReturn('Run backup');
        $inspector->method('hasOptions')->willReturn(false);

        $options = $this->createMock(OptionsInterface::class);
        $options->method('normalize')->willReturn([]);

        $renderer = new CommandHelpRenderer($console, $container, $discovery, $inspector, $options);

        $code = $renderer->render('backup-database');

        $this->assertSame(0, $code);
        $out = implode("\n", $lines);
        $this->assertStringContainsString('backup-database', $out);
        $this->assertStringContainsString('backup-database:run-task', $out);
    }
}

class HelpRendererFixtureCommand
{
    public bool $defaultHelpCalled = false;

    /**
     * Run default.
     *
     * @param bool $foo Whether to do foo.
     * @param int $bar Bar count.
     */
    public function defaultAction(bool $foo = true, int $bar = 2): void
    {
    }

    public function defaultHelp(): void
    {
        $this->defaultHelpCalled = true;
    }

    /**
     * Run second.
     *
     * @param string $name User name.
     */
    public function secondAction(string $name = 'x'): void
    {
    }
}

class HelpRendererMultiWordFixtureCommand
{
    public function runTaskAction(): void
    {
    }
}
