<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Switon\Cli\Console;
use Switon\Cli\Tests\TestCase;
use Switon\Console\Colors;

/**
 * Test cases for Console class.
 *
 * Tests public API of console without accessing protected properties.
 */
class ConsoleTest extends TestCase
{
    protected Console $console;

    protected function setUp(): void
    {
        parent::setUp();
        $this->console = $this->container->make(Console::class);
    }

    public function testColorConstants(): void
    {
        // Arrange & Act & Assert - Verify color constants are defined correctly in Colors class
        $this->assertSame(0x01, Colors::FC_BLACK);
        $this->assertSame(0x02, Colors::FC_RED);
        $this->assertSame(0x04, Colors::FC_GREEN);
        $this->assertSame(0x08, Colors::FC_YELLOW);
        $this->assertSame(0x10, Colors::FC_BLUE);
        $this->assertSame(0x20, Colors::FC_MAGENTA);
        $this->assertSame(0x40, Colors::FC_CYAN);
        $this->assertSame(0x80, Colors::FC_WHITE);

        $this->assertSame(0x0100, Colors::BC_BLACK);
        $this->assertSame(0x0200, Colors::BC_RED);
        $this->assertSame(0x0400, Colors::BC_GREEN);
        $this->assertSame(0x0800, Colors::BC_YELLOW);
        $this->assertSame(0x1000, Colors::BC_BLUE);
        $this->assertSame(0x2000, Colors::BC_MAGENTA);
        $this->assertSame(0x4000, Colors::BC_CYAN);
        $this->assertSame(0x8000, Colors::BC_WHITE);

        $this->assertSame(0x010000, Colors::AT_BOLD);
        $this->assertSame(0x020000, Colors::AT_ITALICS);
        $this->assertSame(0x040000, Colors::AT_UNDERLINE);
        $this->assertSame(0x080000, Colors::AT_BLINK);
        $this->assertSame(0x100000, Colors::AT_INVERSE);
    }

    public function testColorizeWithNoColor(): void
    {
        // Arrange
        $text = 'Hello World';

        // Act
        $result = $this->console->colorize($text, 0);

        // Assert
        $this->assertStringContainsString($text, $result);
    }

    public function testColorizeWithForegroundColor(): void
    {
        // Arrange
        $text = 'Hello World';

        // Act
        $result = $this->console->colorize($text, Colors::FC_RED);

        // Assert
        $this->assertStringContainsString($text, $result);
        if ($this->console->isSupportColor()) {
            $this->assertStringContainsString("\033[", $result);
        }
    }

    public function testColorizeWithBackgroundColor(): void
    {
        // Arrange
        $text = 'Hello World';

        // Act
        $result = $this->console->colorize($text, Colors::BC_BLUE);

        // Assert
        $this->assertStringContainsString($text, $result);
        if ($this->console->isSupportColor()) {
            $this->assertStringContainsString("\033[", $result);
        }
    }

    public function testColorizeWithAttributes(): void
    {
        // Arrange
        $text = 'Hello World';

        // Act
        $result = $this->console->colorize($text, Colors::AT_BOLD);

        // Assert
        $this->assertStringContainsString($text, $result);
        if ($this->console->isSupportColor()) {
            $this->assertStringContainsString("\033[", $result);
        }
    }

    public function testWriteOutputsMessage(): void
    {
        // Arrange
        $message = 'Test message';

        // Act
        ob_start();
        $this->console->write($message);
        $output = ob_get_clean();

        // Assert
        $this->assertSame($message, $output);
    }

    public function testWriteLnOutputsMessageWithNewline(): void
    {
        // Arrange
        $message = 'Test message';

        // Act
        ob_start();
        $this->console->writeLn($message);
        $output = ob_get_clean();

        // Assert
        $this->assertSame($message . PHP_EOL, $output);
    }

    public function testInfoOutputsColoredMessage(): void
    {
        // Arrange
        $message = 'Info message';

        // Act
        ob_start();
        $this->console->info($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testWarningOutputsColoredMessage(): void
    {
        // Arrange
        $message = 'Warning message';

        // Act
        ob_start();
        $this->console->warning($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testSuccessOutputsColoredMessage(): void
    {
        // Arrange
        $message = 'Success message';

        // Act
        ob_start();
        $this->console->success($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testErrorReturnsExitCode(): void
    {
        // Arrange
        $message = 'Error message';

        // Act
        ob_start();
        $exitCode = $this->console->error($message);
        ob_end_clean();

        // Assert
        $this->assertSame(1, $exitCode);
    }

    public function testErrorReturnsCustomExitCode(): void
    {
        // Arrange
        $message = 'Error message';
        $customCode = 255;

        // Act
        ob_start();
        $exitCode = $this->console->error($message, [], $customCode);
        ob_end_clean();

        // Assert
        $this->assertSame($customCode, $exitCode);
    }

    public function testDebugOutputsMessage(): void
    {
        // Arrange
        $message = 'Debug message';

        // Act
        ob_start();
        $this->console->debug($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testAskOutputsQuestion(): void
    {
        // Arrange
        $question = 'What is your name?';

        // Act
        ob_start();
        $this->console->write($question . ': ');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($question, $output);
    }

    public function testBlockWithErrorType(): void
    {
        // Arrange
        $message = 'An error occurred';

        // Act
        ob_start();
        $this->console->block($message, 'error');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
        $this->assertStringContainsString('═', $output);
        $this->assertStringContainsString('║', $output);
    }

    public function testBlockWithWarningType(): void
    {
        // Arrange
        $message = 'Warning message';

        // Act
        ob_start();
        $this->console->block($message, 'warning');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testBlockWithSuccessType(): void
    {
        // Arrange
        $message = 'Operation successful';

        // Act
        ob_start();
        $this->console->block($message, 'success');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testBlockWithInfoType(): void
    {
        // Arrange
        $message = 'Information';

        // Act
        ob_start();
        $this->console->block($message, 'info');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testBlockWithMultipleMessages(): void
    {
        // Arrange
        $messages = ['Line 1', 'Line 2', 'Line 3'];

        // Act
        ob_start();
        $this->console->block($messages);
        $output = ob_get_clean();

        // Assert
        foreach ($messages as $message) {
            $this->assertStringContainsString($message, $output);
        }
    }

    public function testBlockWithCustomPrefix(): void
    {
        // Arrange
        $message = 'Custom message';
        $prefix = '>>>';

        // Act
        ob_start();
        $this->console->block($message, null, $prefix);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
        $this->assertStringContainsString($prefix, $output);
    }

    public function testBlockWithoutPadding(): void
    {
        // Arrange
        $message = 'No padding';

        // Act
        ob_start();
        $this->console->block($message, null, null, false);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testSectionOutputsHeader(): void
    {
        // Arrange
        $message = 'Section Title';

        // Act
        ob_start();
        $this->console->section($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
        $this->assertStringContainsString('=', $output);
    }

    public function testNoteOutputsMessage(): void
    {
        // Arrange
        $message = 'This is a note';

        // Act
        ob_start();
        $this->console->note($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
        $this->assertStringContainsString('NOTE', $output);
    }

    public function testCautionOutputsMessage(): void
    {
        // Arrange
        $message = 'Be careful';

        // Act
        ob_start();
        $this->console->caution($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
        $this->assertStringContainsString('CAUTION', $output);
    }

    public function testListingOutputsItems(): void
    {
        // Arrange
        $items = ['Item 1', 'Item 2', 'Item 3'];

        // Act
        ob_start();
        $this->console->listing($items);
        $output = ob_get_clean();

        // Assert
        foreach ($items as $item) {
            $this->assertStringContainsString($item, $output);
        }
        $this->assertStringContainsString('-', $output);
    }

    public function testNewLineOutputsMultipleLines(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->newLine(3);
        $output = ob_get_clean();

        // Assert
        $this->assertSame(str_repeat(PHP_EOL, 3), $output);
    }

    public function testLineOutputsMessage(): void
    {
        // Arrange
        $message = 'Line message';

        // Act
        ob_start();
        $this->console->line($message);
        $output = ob_get_clean();

        // Assert
        $this->assertSame($message . PHP_EOL, $output);
    }

    public function testLineOutputsEmptyLine(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->line();
        $output = ob_get_clean();

        // Assert
        $this->assertSame(PHP_EOL, $output);
    }

    public function testColorizeWithWidth(): void
    {
        // Arrange
        $text = 'Test';
        $width = 20;

        // Act
        $result = $this->console->colorize($text, 0, $width);

        // Assert - width should be applied
        $this->assertGreaterThanOrEqual($width, strlen($result));
    }

    public function testColorizeWithCombinedOptions(): void
    {
        // Arrange
        $text = 'Combined';

        // Act
        $result = $this->console->colorize(
            $text,
            Colors::FC_RED | Colors::BC_YELLOW | Colors::AT_BOLD
        );

        // Assert
        $this->assertStringContainsString($text, $result);
    }

    public function testIsSupportColorReturnsBoolean(): void
    {
        // Arrange & Act
        $result = $this->console->isSupportColor();

        // Assert
        $this->assertIsBool($result);
    }

    public function testIsSupportColorReturnsFalseWhenNoColorIsSet(): void
    {
        $console = new TestableConsole(['NO_COLOR' => '1'], true, true);

        $this->assertFalse($console->isSupportColor());
    }

    public function testIsSupportColorReturnsFalseWhenOutputIsNotTty(): void
    {
        $console = new TestableConsole([], false, true);

        $this->assertFalse($console->isSupportColor());
    }

    public function testIsSupportColorReturnsTrueOnUnixTtyWithoutNoColor(): void
    {
        $console = new TestableConsole([], true, true);

        $this->assertTrue($console->isSupportColor());
    }

    public function testProgressWithIntValue(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->progress('Progress: {value}', 50);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('50', $output);
    }

    public function testProgressWithFloatValue(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->progress('Progress: {value}', 75.5);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('75.50', $output);
    }

    public function testProgressWithNullValue(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->progress('Done');
        $output = ob_get_clean();

        // Assert - should contain newline when value is null
        $this->assertStringContainsString(PHP_EOL, $output);
    }

    public function testProgressWithStringValue(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->progress('Status: {value}', 'completed');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('completed', $output);
    }

    public function testWriteWithContextInterpolation(): void
    {
        // Arrange
        $message = 'Hello {name}';
        $context = ['name' => 'World'];

        // Act
        ob_start();
        $this->console->write($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('World', $output);
    }

    public function testWriteWithColorOptions(): void
    {
        // Arrange
        $message = 'Colored text';

        // Act
        ob_start();
        $this->console->write($message, [], Colors::FC_RED);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testWriteLnWithContext(): void
    {
        // Arrange
        $message = 'Value: {value}';
        $context = ['value' => '123'];

        // Act
        ob_start();
        $this->console->writeLn($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('123', $output);
        $this->assertStringContainsString(PHP_EOL, $output);
    }

    public function testDebugWithContext(): void
    {
        // Arrange
        $message = 'Debug: {info}';
        $context = ['info' => 'test'];

        // Act
        ob_start();
        $this->console->debug($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('test', $output);
    }

    public function testInfoWithContext(): void
    {
        // Arrange
        $message = 'Info: {data}';
        $context = ['data' => 'value'];

        // Act
        ob_start();
        $this->console->info($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('value', $output);
    }

    public function testWarningWithContext(): void
    {
        // Arrange
        $message = 'Warning: {msg}';
        $context = ['msg' => 'alert'];

        // Act
        ob_start();
        $this->console->warning($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('alert', $output);
    }

    public function testSuccessWithContext(): void
    {
        // Arrange
        $message = 'Success: {result}';
        $context = ['result' => 'ok'];

        // Act
        ob_start();
        $this->console->success($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('ok', $output);
    }

    public function testErrorWithContext(): void
    {
        // Arrange
        $message = 'Error: {error}';
        $context = ['error' => 'failed'];

        // Act
        ob_start();
        $exitCode = $this->console->error($message, $context);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('failed', $output);
        $this->assertSame(1, $exitCode);
    }

    public function testSampleColorizerExecutes(): void
    {
        // Arrange & Act
        ob_start();
        $this->console->sampleColorizer();
        $output = ob_get_clean();

        // Assert - should output color samples and progress demo
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Switon', $output);
    }

    public function testBlockWithMultibyteCharacters(): void
    {
        // Arrange
        $message = '中文测试消息';

        // Act
        ob_start();
        $this->console->block($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }

    public function testSectionWithMultibyteCharacters(): void
    {
        // Arrange
        $message = '章节标题';

        // Act
        ob_start();
        $this->console->section($message);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString($message, $output);
    }
}

class TestableConsole extends Console
{
    /**
     * @param array<string, string> $env
     */
    public function __construct(
        protected array $env,
        protected ?bool $tty,
        protected bool  $unix,
    ) {
    }

    protected function isUnix(): bool
    {
        return $this->unix;
    }

    protected function getEnv(string $name): string|false
    {
        return $this->env[$name] ?? false;
    }

    protected function isOutputTty(): ?bool
    {
        return $this->tty;
    }
}
