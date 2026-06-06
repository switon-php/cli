<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Cli\Console;
use Switon\Console\TerminalWidthDetectorInterface;
use Stringable;

#[AllowMockObjectsWithoutExpectations]
class ConsoleInteractiveTest extends TestCase
{
    public function testConfirmAcceptsDefaultOnEmptyInputAndRetriesOnInvalid(): void
    {
        $c = new TestableInteractiveConsole(['', 'maybe', 'y']);
        $this->injectDeps($c);

        $result = $c->confirm('Continue?', true);

        $this->assertTrue($result, 'empty input should accept default true');

        $result2 = $c->confirm('Continue?', false);
        $this->assertTrue($result2, 'after invalid input, y should be accepted');
        $this->assertNotEmpty($c->capturedErrors, 'invalid input should emit error');
    }

    public function testChoiceReturnsDefaultOnEmptyAndRetriesOnInvalid(): void
    {
        $c = new TestableInteractiveConsole(['', '9', '2']);
        $this->injectDeps($c);

        $opt = ['apple', 'banana', 'cherry'];
        $result = $c->choice('Pick one', $opt, 0);
        $this->assertSame('apple', $result);

        $result2 = $c->choice('Pick one', $opt, null);
        $this->assertSame('banana', $result2);
        $this->assertNotEmpty($c->capturedErrors);
    }

    public function testSecretFallsBackWhenSttyUnavailable(): void
    {
        $c = new TestableInteractiveConsole(['topsecret']);
        $this->injectDeps($c);
        $c->shellOutputs = [
            'stty -g 2>/dev/null' => '',
        ];

        $value = $c->secret('Password');
        $this->assertSame('topsecret', $value);
        $this->assertNotEmpty($c->capturedWarnings, 'should warn when stty is unavailable');
    }

    public function testBlockWrapsLongMessages(): void
    {
        $c = new TestableInteractiveConsole([]);
        $this->injectDeps($c, width: 60);

        $c->block('this is a very long message that must wrap across multiple lines', 'INFO', 'I', true);

        $this->assertNotEmpty($c->capturedWrites);
        $joined = implode("\n", $c->capturedWrites);
        $this->assertStringContainsString('I this is a very long message', $joined);
    }

    private function injectDeps(TestableInteractiveConsole $c, int $width = 80): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);
        $widthDetector = $this->createMock(TerminalWidthDetectorInterface::class);
        $widthDetector->method('getWidth')->willReturn($width);

        $c->setDependencies($dispatcher, $widthDetector);
    }
}

class TestableInteractiveConsole extends Console
{
    /** @var list<string> */
    public array $inputs;
    /** @var list<string> */
    public array $capturedWrites = [];
    /** @var list<string> */
    public array $capturedErrors = [];
    /** @var list<string> */
    public array $capturedWarnings = [];
    /** @var array<string, string|null> */
    public array $shellOutputs = [];

    /** @param list<string> $inputs */
    public function __construct(array $inputs)
    {
        $this->inputs = $inputs;
    }

    public function setDependencies(
        EventDispatcherInterface       $dispatcher,
        TerminalWidthDetectorInterface $widthDetector,
    ): void {
        $this->eventDispatcher = $dispatcher;
        $this->widthDetector = $widthDetector;
    }

    public function write(string|Stringable $message, array $context = [], int $options = 0): void
    {
        // Keep branch coverage for interpolation and exception printing in parent tested elsewhere.
        $this->capturedWrites[] = (string)$message;
    }

    public function error(string|Stringable $message, array $context = [], int $code = 1): int
    {
        $this->capturedErrors[] = (string)$message;
        return $code;
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->capturedWarnings[] = (string)$message;
    }

    public function read(): string
    {
        return array_shift($this->inputs) ?? '';
    }

    protected function runShellCommand(string $command): ?string
    {
        return $this->shellOutputs[$command] ?? null;
    }
}
