<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Exception;
use RuntimeException;
use Switon\Cli\ErrorHandler;
use Switon\Cli\Tests\TestCase;
use Switon\Testing\Mock\MockConsole;
use Throwable;

/**
 * Test cases for ErrorHandler class.
 *
 * Tests error handling and exception logging.
 */
class ErrorHandlerTest extends TestCase
{
    protected ErrorHandler $errorHandler;
    protected MockConsole $console;

    protected function setUp(): void
    {
        parent::setUp();
        $this->console = $this->container->get(MockConsole::class);
        $this->errorHandler = $this->container->make(ErrorHandler::class);
    }

    public function testHandleException(): void
    {
        // Arrange
        $exception = new Exception('Test exception message');
        $this->console->clearOutput();

        // Act
        $this->errorHandler->handle($exception);

        // Assert
        $output = implode("\n", $this->console->getOutput());
        $this->assertNotEmpty($output, 'Error handler should output exception');
        $this->assertStringContainsString(
            'Error: Test exception message',
            $output,
            'Output should contain formatted exception message'
        );
        $this->assertStringContainsString(
            'Type: Exception',
            $output,
            'Output should contain exception type'
        );
    }

    public function testHandleRuntimeException(): void
    {
        // Arrange
        $exception = new RuntimeException('Runtime error occurred');
        $this->console->clearOutput();

        // Act
        $this->errorHandler->handle($exception);

        // Assert
        $output = implode("\n", $this->console->getOutput());
        $this->assertNotEmpty($output, 'Should output runtime exception');
        $this->assertStringContainsString(
            'Error: Runtime error occurred',
            $output,
            'Output should contain formatted error message'
        );
        $this->assertStringContainsString(
            'Type: RuntimeException',
            $output,
            'Output should contain exception type'
        );
    }

    public function testHandleExceptionWithStackTrace(): void
    {
        // Arrange
        $exception = new Exception('Error with trace');
        $this->console->clearOutput();

        // Act
        $this->errorHandler->handle($exception);

        // Assert
        $output = implode("\n", $this->console->getOutput());
        $this->assertStringContainsString(
            'Error: Error with trace',
            $output,
            'Should contain formatted exception message'
        );
        // Stack trace is only shown in verbose mode
    }

    public function testHandleExceptionDoesNotThrow(): void
    {
        // Arrange
        $exception = new Exception('Safe to handle');
        $this->console->clearOutput();

        // Act & Assert - Should not throw any exception
        try {
            $this->errorHandler->handle($exception);
            $this->assertTrue(true, 'Error handler should not throw');
        } catch (Throwable $e) {
            $this->fail('Error handler should not throw exceptions: ' . $e->getMessage());
        }
    }

    public function testHandleMultipleExceptions(): void
    {
        // Arrange
        $exception1 = new Exception('First error');
        $exception2 = new Exception('Second error');

        // Act
        $this->console->clearOutput();
        $this->errorHandler->handle($exception1);
        $output1 = implode("\n", $this->console->getOutput());

        $this->console->clearOutput();
        $this->errorHandler->handle($exception2);
        $output2 = implode("\n", $this->console->getOutput());

        // Assert
        $this->assertStringContainsString(
            'Error: First error',
            $output1,
            'First output should contain formatted first error'
        );
        $this->assertStringContainsString(
            'Error: Second error',
            $output2,
            'Second output should contain formatted second error'
        );
    }
}
