<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Switon\Cli\Command\CommandHelpRenderer;
use Switon\Cli\OptionsInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Core\ConsoleInterface;
use DateTimeImmutable;

#[AllowMockObjectsWithoutExpectations]
class CommandHelpRendererTest extends TestCase
{
    protected ConsoleInterface&MockObject $console;
    protected ContainerInterface&MockObject $container;
    protected CommandDiscoveryInterface&MockObject $commandDiscovery;
    protected CommandInspectorInterface&MockObject $commandInspector;
    protected OptionsInterface&MockObject $options;
    protected CommandHelpRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->console = $this->createMock(ConsoleInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->commandDiscovery = $this->createMock(CommandDiscoveryInterface::class);
        $this->commandInspector = $this->createMock(CommandInspectorInterface::class);
        $this->options = $this->createMock(OptionsInterface::class);

        $this->renderer = new CommandHelpRenderer(
            $this->console,
            $this->container,
            $this->commandDiscovery,
            $this->commandInspector,
            $this->options
        );

        $this->console->method('colorize')
            ->willReturnCallback(static fn (string $text): string => $text);
    }

    public function testRenderReturnsErrorWhenCommandIsNotFound(): void
    {
        $this->commandDiscovery->method('discover')->willReturn([]);

        $this->console->expects($this->once())
            ->method('error')
            ->with('{command} Command not found', ['command' => 'unknown'])
            ->willReturn(1);

        $code = $this->renderer->render('unknown');

        $this->assertSame(1, $code);
    }

    public function testRenderReturnsErrorWhenCommandClassCannotBeReflected(): void
    {
        $this->commandDiscovery->method('discover')
            ->willReturn(['broken-name' => 'Switon\\Cli\\Tests\\Unit\\MissingCommandClass']);
        $this->commandInspector->method('getCommandDescription')->willReturn('');

        $this->console->expects($this->once())
            ->method('error')
            ->with('Cannot reflect command {class}', ['class' => 'Switon\\Cli\\Tests\\Unit\\MissingCommandClass'])
            ->willReturn(1);

        $code = $this->renderer->render('broken-name');

        $this->assertSame(1, $code);
    }

    public function testRenderCallsSingleActionHelpMethodWhenPresent(): void
    {
        DummySingleActionCommand::$helpCalls = 0;

        $this->commandDiscovery->method('discover')
            ->willReturn(['dummy-command' => DummySingleActionCommand::class]);

        $this->commandInspector->method('getCommandDescription')
            ->with(DummySingleActionCommand::class)
            ->willReturn('dummy description');

        $this->commandInspector->method('getMethodDescription')
            ->willReturnCallback(static function (ReflectionMethod $method): string {
                return $method->getName() === 'defaultAction' ? 'dummy action' : '';
            });

        $this->container->expects($this->once())
            ->method('get')
            ->with(DummySingleActionCommand::class)
            ->willReturn(new DummySingleActionCommand());

        $lines = [];
        $this->console->expects($this->exactly(2))
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $code = $this->renderer->render('dummy-command');

        $this->assertSame(0, $code);
        $this->assertSame(1, DummySingleActionCommand::$helpCalls);
        $this->assertStringContainsString('dummy-command', $lines[0]);
        $this->assertStringContainsString('dummy-command:default', $lines[1]);
    }

    public function testRenderSingleActionWithoutOptionsReturnsZero(): void
    {
        $this->commandDiscovery->method('discover')
            ->willReturn(['dummy-command' => DummyNoOptionCommand::class]);
        $this->commandInspector->method('getCommandDescription')->willReturn('');
        $this->commandInspector->method('getMethodDescription')->willReturn('');
        $this->commandInspector->method('hasOptions')->willReturn(false);

        $this->console->expects($this->exactly(2))->method('writeLn');
        $this->container->expects($this->never())->method('get');

        $code = $this->renderer->render('dummy-command');

        $this->assertSame(0, $code);
    }

    public function testRenderActionHelpPrintsActionNameAndTypedDefaults(): void
    {
        $lines = [];
        $this->console->method('writeLn')->willReturnCallback(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $this->options->method('normalize')->willReturn([
            'f' => 'flag',
            'c' => 'count',
            'r' => 'ratio',
            't' => 'title',
            'i' => 'items',
        ]);
        $this->commandInspector->method('getMethodDescription')->willReturn('Rich action');

        $renderer = new ExposedCommandHelpRenderer(
            $this->console,
            $this->container,
            $this->commandDiscovery,
            $this->commandInspector,
            $this->options
        );

        $reflection = new ReflectionMethod(DummyTypedDefaultsCommand::class, 'typedAction');
        $renderer->exposedRenderActionHelp($reflection, 'typedAction', false);

        $out = implode("\n", $lines);
        $this->assertStringContainsString('typed', $out);
        $this->assertStringContainsString('--flag, -f', $out);
        $this->assertStringContainsString('--count, -c', $out);
        $this->assertStringContainsString('--ratio, -r', $out);
        $this->assertStringContainsString('--title, -t', $out);
        $this->assertStringContainsString('--items, -i', $out);
        $this->assertStringContainsString('true', $out);
        $this->assertStringContainsString('2.5', $out);
        $this->assertStringContainsString('"demo"', $out);
    }
}

class DummySingleActionCommand
{
    public static int $helpCalls = 0;

    /**
     * Default action.
     */
    public function defaultAction(string $name = ''): void
    {
    }

    public function defaultHelp(): void
    {
        self::$helpCalls++;
    }
}

class DummyNoOptionCommand
{
    public function defaultAction(DateTimeImmutable $dt): void
    {
    }
}

class DummyTypedDefaultsCommand
{
    /**
     * Typed demo action.
     *
     * @param bool $flag Toggle behavior.
     * @param int $count Number of retries.
     * @param float $ratio Ratio value.
     * @param string $title Display title.
     * @param array $items Item list.
     */
    public function typedAction(
        bool   $flag = true,
        int    $count = 3,
        float  $ratio = 2.5,
        string $title = 'demo',
        array  $items = ['a', 'b']
    ): void {
    }
}

class ExposedCommandHelpRenderer extends CommandHelpRenderer
{
    public function exposedRenderActionHelp(ReflectionMethod $rMethod, string $method, bool $skipActionName = false): void
    {
        $this->renderActionHelp($rMethod, $method, $skipActionName);
    }
}
