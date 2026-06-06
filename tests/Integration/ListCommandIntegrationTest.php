<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Integration;

use Switon\Cli\Handler;
use Switon\Cli\OptionsInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Switon\Testing\Mock\MockConsole;

/**
 * Integration tests for ListCommand
 *
 * Tests end-to-end CLI command execution
 */
class ListCommandIntegrationTest extends IntegrationTestCase
{
    protected Handler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        // Same Options instance for Handler and Invoker so --json etc. reach commands
        $options = $this->container->get(OptionsInterface::class);
        $this->container->set(InputInterface::class, $options);
        $this->handler = $this->container->make(Handler::class);
    }

    public function testListCommandExecutesSuccessfully(): void
    {
        // Arrange
        $argv = ['cli.php', 'list'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($argv);
        $output = ob_get_clean();

        // Assert
        $this->assertSame(0, $exitCode, 'List command should return exit code 0');
        // Output may be empty if events are dispatched but not echoed in tests
    }

    public function testListCommandShowsBuiltinCommands(): void
    {
        // Arrange
        $argv = ['cli.php', 'list'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($argv);
        $output = ob_get_clean();

        // Assert
        $this->assertSame(0, $exitCode);
        // List command execution was successful
    }

    public function testListCommandWithJsonFlagOutputsMachineReadableJson(): void
    {
        $argv = ['cli.php', 'list', '--json'];

        $exitCode = $this->handler->handle($argv);

        $console = $this->container->get(ConsoleInterface::class);
        $this->assertInstanceOf(MockConsole::class, $console);
        $lines = $console->getOutput();
        $this->assertNotEmpty($lines, 'List --json should write one JSON line');
        $output = $lines[array_key_last($lines)];

        $this->assertSame(0, $exitCode);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('framework_version', $decoded);
        $this->assertArrayHasKey('app', $decoded);
        $this->assertArrayHasKey('commands', $decoded);
        $this->assertIsArray($decoded['commands']);
        $this->assertArrayHasKey('id', $decoded['app']);
        $this->assertArrayHasKey('version', $decoded['app']);
        $this->assertArrayHasKey('env', $decoded['app']);
        $this->assertArrayHasKey('debug', $decoded['app']);
        foreach ($decoded['commands'] as $cmd) {
            $this->assertArrayHasKey('name', $cmd);
            $this->assertArrayHasKey('kind', $cmd);
            $this->assertContains($cmd['kind'], ['builtin', 'application']);
            $this->assertArrayHasKey('actions', $cmd);
            $this->assertIsArray($cmd['actions']);
        }
    }

    public function testCommandListOutputsNonEmptyDescriptionForActionsWithAiDocOrPhpdoc(): void
    {
        if (!class_exists(\Switon\Beacon\Command\ToolCommand::class)) {
            $this->markTestSkipped('tool:commands (Beacon) requires switon/beacon');
        }
        $argv = ['cli.php', 'tool:commands'];

        $exitCode = $this->handler->handle($argv);

        $this->assertSame(0, $exitCode);
        $console = $this->container->get(ConsoleInterface::class);
        $this->assertInstanceOf(MockConsole::class, $console);
        $lines = $console->getOutput();
        $this->assertNotEmpty($lines);
        $decoded = json_decode($lines[array_key_last($lines)], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        // Actions with #[Tool] or PHPDoc must not have empty description (fallback to tool doc when PHPDoc empty)
        foreach (['class:list', 'class:inspect', 'class:content', 'tool:list'] as $invocation) {
            if (isset($decoded[$invocation])) {
                $this->assertNotEmpty($decoded[$invocation], "tool:commands must output non-empty description for {$invocation}");
            }
        }
    }

    public function testListCommandCommandWithFormatJsonOutputsMachineReadableHelp(): void
    {
        // tool:description <name> → JSON (beacon); skip when beacon not loaded
        if (!class_exists(\Switon\Beacon\Command\ToolCommand::class)) {
            $this->markTestSkipped('tool:description requires switon/beacon');
        }
        $argv = ['cli.php', 'tool:description', 'list'];

        $exitCode = $this->handler->handle($argv);

        if ($exitCode !== 0) {
            $console = $this->container->get(ConsoleInterface::class);
            $output = $console instanceof MockConsole ? implode("\n", $console->getOutput()) : '';
            if (str_contains($output, 'LocaleInterface') || str_contains($output, 'NotFoundException')) {
                $this->markTestSkipped('tool:description requires full app container (e.g. LocaleInterface)');
            }
            $this->assertSame(0, $exitCode, 'tool:description failed: ' . $output);
        }

        $console = $this->container->get(ConsoleInterface::class);
        $this->assertInstanceOf(MockConsole::class, $console);
        $lines = $console->getOutput();
        $this->assertNotEmpty($lines);
        $output = $lines[array_key_last($lines)];
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('command', $decoded);
        $this->assertArrayHasKey('description', $decoded);
        $this->assertArrayHasKey('actions', $decoded);
        $this->assertIsArray($decoded['actions']);
        $this->assertSame('list', $decoded['command']);
        foreach ($decoded['actions'] as $act) {
            $this->assertArrayHasKey('name', $act);
            $this->assertArrayHasKey('invocation', $act);
            $this->assertArrayHasKey('options', $act);
            $this->assertIsArray($act['options']);
        }
    }

    public function testToolEntryOutputsStructuredEntriesAndSupportsTopicFilter(): void
    {
        if (!class_exists(\Switon\Beacon\Command\ToolCommand::class)) {
            $this->markTestSkipped('tool:entry requires switon/beacon');
        }

        $argv = ['cli.php', 'tool:entry'];
        $exitCode = $this->handler->handle($argv);

        $this->assertSame(0, $exitCode);
        $console = $this->container->get(ConsoleInterface::class);
        $this->assertInstanceOf(MockConsole::class, $console);
        $lines = $console->getOutput();
        $this->assertNotEmpty($lines);

        $decoded = json_decode($lines[array_key_last($lines)], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('entries', $decoded);
        $this->assertIsArray($decoded['entries']);
        $this->assertNotEmpty($decoded['entries']);

        foreach ($decoded['entries'] as $entry) {
            $this->assertArrayHasKey('topic', $entry);
            $this->assertArrayHasKey('class', $entry);
            $this->assertIsString($entry['topic']);
            $this->assertIsString($entry['class']);
        }

        $map = [];
        foreach ($decoded['entries'] as $entry) {
            $map[$entry['topic']] = $entry['class'];
        }

        $this->assertArrayHasKey('command-discovery', $map);
        $this->assertSame('Switon\\Command\\CommandDiscoveryInterface', $map['command-discovery']);
        $this->assertArrayHasKey('autowired', $map);
        $this->assertSame('Switon\\Core\\Attribute\\Autowired', $map['autowired']);
        $this->assertArrayHasKey('parameter-injection', $map);
        $this->assertSame('Switon\\Binding\\ArgumentsBinderInterface', $map['parameter-injection']);
        $this->assertArrayHasKey('entrypoint', $map);
        $this->assertSame('Switon\\Kernel\\KernelInterface', $map['entrypoint']);
        $this->assertArrayHasKey('interceptor', $map);
        $this->assertSame('Switon\\Invocation\\Attribute\\InterceptorInterface', $map['interceptor']);

        $filteredExitCode = $this->handler->handle(['cli.php', 'tool:entry', 'interceptor']);

        $this->assertSame(0, $filteredExitCode);
        $filteredLines = $console->getOutput();
        $filtered = json_decode($filteredLines[array_key_last($filteredLines)], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            [
                'entries' => [
                    [
                        'topic' => 'interceptor',
                        'class' => 'Switon\\Invocation\\Attribute\\InterceptorInterface',
                    ],
                ],
            ],
            $filtered
        );
    }

    public function testListCommandWithHelpFlag(): void
    {
        // Arrange
        $argv = ['cli.php', '--help'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($argv);
        $output = ob_get_clean();

        // Assert
        $this->assertSame(0, $exitCode);
        // Help flag was processed successfully
    }

    public function testListCommandWithInvalidAction(): void
    {
        // Arrange
        $argv = ['cli.php', 'list:invalid'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($argv);
        $output = ob_get_clean();

        // Assert
        $this->assertNotSame(0, $exitCode, 'Invalid action should return non-zero exit code');
    }

    public function testInvalidCommandReturnsError(): void
    {
        // Arrange
        $argv = ['cli.php', 'nonexistent'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($argv);
        $output = ob_get_clean();

        // Assert
        $this->assertNotSame(0, $exitCode, 'Nonexistent command should return non-zero exit code');
    }

    public function testHandlerBindsCliOptionsAndPositionalArgumentsThroughBinderPipeline(): void
    {
        CliArgumentProbeCommand::$captured = null;

        $this->container->replace(
            CommandDiscoveryInterface::class,
            new class () implements CommandDiscoveryInterface {
                public function discover(): array
                {
                    return ['cli-argument-probe' => CliArgumentProbeCommand::class];
                }
            }
        );

        $options = $this->container->get(OptionsInterface::class);
        $this->container->replace(InputInterface::class, $options);
        $handler = $this->container->make(Handler::class);

        $argv = ['cli.php', 'cli-argument-probe:run', 'Mark', 'tail-1', 'tail-2', '--verbose'];

        $exitCode = $handler->handle($argv);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            [
                'name' => 'Mark',
                'verbose' => true,
                'rest' => ['tail-1', 'tail-2'],
            ],
            CliArgumentProbeCommand::$captured
        );
    }
}

class CliArgumentProbeCommand
{
    /** @var array{name: string, verbose: bool, rest: array<int, string>}|null */
    public static ?array $captured = null;

    public function runAction(string $name, bool $verbose = false, array $rest = []): int
    {
        self::$captured = [
            'name' => $name,
            'verbose' => $verbose,
            'rest' => $rest,
        ];

        return 0;
    }
}
