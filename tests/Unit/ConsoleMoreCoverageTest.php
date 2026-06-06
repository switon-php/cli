<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Switon\Cli\Console;
use Switon\Console\TerminalWidthDetectorInterface;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
class ConsoleMoreCoverageTest extends TestCase
{
    public function testAskAndConfirmAndChoiceExerciseBranches(): void
    {
        $c = $this->makeHarness(['x', 'y', 'z', 'n', '', '2'], 50);

        ob_start();
        $c->ask('Name');
        $c->ask('Ready?');
        $c->ask('Value:');

        $confirmed = $c->confirm('Proceed?', true);
        $picked = $c->choice('Pick', ['a', 'b', 'c'], 0);
        $picked2 = $c->choice('Pick', ['a', 'b', 'c'], null);
        $out = ob_get_clean();

        $this->assertStringContainsString('Name:', $out);
        $this->assertStringContainsString('Ready?', $out);
        $this->assertStringContainsString('Value:', $out);

        $this->assertFalse($confirmed, 'n should be negative');
        $this->assertSame('a', $picked, 'empty input should choose default');
        $this->assertSame('b', $picked2, 'numeric 2 should pick second');
    }

    public function testSecretUsesSttyWhenAvailable(): void
    {
        $c = $this->makeHarness(['s3cr3t']);
        $c->shellOutputs = [
            'stty -g 2>/dev/null' => 'saved-mode',
            'stty -echo 2>/dev/null' => '',
            'stty ' . escapeshellarg('saved-mode') . ' 2>/dev/null' => '',
        ];

        ob_start();
        $value = $c->secret('Password');
        $out = ob_get_clean();

        $this->assertSame('s3cr3t', $value);
        $this->assertStringContainsString('Password:', $out);
    }

    public function testTableRendersHeadersAndRowsAndHandlesNulls(): void
    {
        $c = $this->makeHarness([]);

        ob_start();
        $c->table(['a', 'b'], [[null, 2], ['x', null]], 3, true);
        $out = ob_get_clean();

        $this->assertStringContainsString('#', $out);
        $this->assertStringContainsString('-', $out);
        $this->assertStringContainsString('x', $out);
    }

    public function testWriteAppendsExceptionOutputAfterInterpolation(): void
    {
        $c = $this->makeHarness([]);

        ob_start();
        $c->write(
            'Hello {name}',
            ['name' => 'world', 'exception' => new RuntimeException('boom')]
        );
        $out = ob_get_clean();

        $this->assertStringContainsString('Hello world', $out);
        $this->assertStringContainsString('RuntimeException', $out);
        $this->assertStringContainsString('boom', $out);
    }

    public function testChoiceSupportsAssociativeKeysAndNumericSelection(): void
    {
        $c = $this->makeHarness(['2', 'beta']);

        ob_start();
        $selected = $c->choice('Pick', ['alpha' => 'A', 'beta' => 'B'], null);
        $out = ob_get_clean();

        $this->assertSame('beta', $selected);
        $this->assertStringContainsString('alpha', $out);
        $this->assertStringContainsString('beta', $out);
    }

    public function testProgressWritesImmediateUpdateForNullValue(): void
    {
        $c = $this->makeHarness([], 40);

        ob_start();
        $c->progress('Working');
        $out = ob_get_clean();

        $this->assertStringContainsString("\r", $out);
        $this->assertStringContainsString('Working', $out);
    }

    public function testConfirmRetriesOnInvalidInputThenAcceptsAffirmative(): void
    {
        $c = $this->makeHarness(['maybe', 'y']);

        ob_start();
        $ok = $c->confirm('Proceed?', true);
        $out = ob_get_clean();

        $this->assertTrue($ok);
        $this->assertStringContainsString('Invalid input', $out);
    }

    public function testChoiceWithoutDefaultRetriesAfterEmptyInput(): void
    {
        $c = $this->makeHarness(['', '1']);

        ob_start();
        $picked = $c->choice('Pick', ['a', 'b', 'c'], null);
        $out = ob_get_clean();

        $this->assertSame('a', $picked);
        $this->assertStringContainsString('Please select an option', $out);
    }

    public function testChoiceRetriesAfterInvalidSelection(): void
    {
        $c = $this->makeHarness(['nope', 'beta']);

        ob_start();
        $picked = $c->choice('Pick', ['alpha' => 'A', 'beta' => 'B'], null);
        $out = ob_get_clean();

        $this->assertSame('beta', $picked);
        $this->assertStringContainsString('Invalid selection', $out);
    }

    public function testProgressUsesCustomStringPercentToken(): void
    {
        $c = $this->makeHarness([], 40);

        ob_start();
        $c->progress('State {value}', 'loading');
        $out = ob_get_clean();

        $this->assertStringContainsString('loading', $out);
    }

    public function testSampleColorizerOutputsPaletteAndMessages(): void
    {
        $c = $this->makeHarness([], 120);

        ob_start();
        $c->sampleColorizer();
        $out = ob_get_clean();

        $this->assertStringContainsString('This is info text', $out);
        $this->assertStringContainsString('Progress bar example', $out);
    }

    public function testColorizePadsWhenColorDisabled(): void
    {
        $c = $this->makeHarness([]);
        $c->forceNoColor = true;

        $this->assertSame('hi      ', $c->colorize('hi', 0, 8));
    }

    public function testWrapByDisplayWidthBreaksLongTokenByCharacters(): void
    {
        $c = $this->makeHarness([]);

        $r = new ReflectionClass(Console::class);
        $m = $r->getMethod('wrapByDisplayWidth');

        /** @var list<string> $lines */
        $lines = $m->invoke($c, 'abcdefghij', 3);
        $this->assertGreaterThan(1, count($lines));
    }

    private function makeHarness(array $inputs, int $width = 80): RealConsoleHarness
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn (object $e): object => $e);
        $widthDetector = $this->createMock(TerminalWidthDetectorInterface::class);
        $widthDetector->method('getWidth')->willReturn($width);

        return new RealConsoleHarness($inputs, $dispatcher, $widthDetector);
    }
}

class RealConsoleHarness extends Console
{
    /** @var list<string> */
    public array $inputs;

    /** @var array<string, string|null> */
    public array $shellOutputs = [];

    public bool $forceNoColor = false;

    /** @param list<string> $inputs */
    public function __construct(
        array                           $inputs,
        ?EventDispatcherInterface       $dispatcher = null,
        ?TerminalWidthDetectorInterface $widthDetector = null,
    ) {
        $this->inputs = $inputs;
        if ($dispatcher !== null) {
            $this->eventDispatcher = $dispatcher;
        }
        if ($widthDetector !== null) {
            $this->widthDetector = $widthDetector;
        }
    }

    protected function getEnv(string $name): string|false
    {
        if ($this->forceNoColor && $name === 'NO_COLOR') {
            return '1';
        }

        return parent::getEnv($name);
    }

    public function read(): string
    {
        return array_shift($this->inputs) ?? '';
    }

    protected function runShellCommand(string $command): ?string
    {
        return $this->shellOutputs[$command] ?? '';
    }
}
