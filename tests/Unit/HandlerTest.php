<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Switon\Cli\Command\CommandHelpRenderer;
use Switon\Cli\CommandHelpRendererInterface;
use Switon\Cli\Handler;
use Switon\Cli\ServerInterface;
use Switon\Cli\Tests\TestCase;

/**
 * Test cases for Handler class.
 *
 * Tests command routing, argument parsing, and command execution logic.
 */
class HandlerTest extends TestCase
{
    protected Handler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(CommandHelpRendererInterface::class, CommandHelpRenderer::class);
        $this->handler = $this->container->make(Handler::class);
    }

    public function testHandleSimpleCommand(): void
    {
        // Arrange
        $args = ['cli', 'list'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'List command should execute successfully');
    }

    public function testHandleCommandWithAction(): void
    {
        // Arrange
        $args = ['cli', 'list:default'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'List:default command should execute successfully');
    }

    public function testHandleNonExistentCommand(): void
    {
        // Arrange
        $args = ['cli', 'nonexistent'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($args);
        ob_end_clean();

        // Assert
        $this->assertSame(ServerInterface::EXIT_COMMAND_NOT_FOUND, $exitCode, 'Non-existent command should return 251');
    }

    public function testHandleNonExistentAction(): void
    {
        // Arrange
        $args = ['cli', 'list:nonexistent'];

        // Act
        ob_start();
        $exitCode = $this->handler->handle($args);
        ob_end_clean();

        // Assert
        $this->assertSame(ServerInterface::EXIT_ACTION_NOT_FOUND, $exitCode, 'Non-existent action should return 252');
    }

    public function testHandleEmptyArgs(): void
    {
        // Arrange
        $args = ['cli'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'Empty args should show command list');
    }

    public function testHandleHelpFlag(): void
    {
        // Arrange
        $args = ['cli', '--help'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'Help flag should show command list');
    }

    public function testHandleCommandWithHelpFlag(): void
    {
        // Arrange
        $args = ['cli', 'list', '--help'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'Command help should execute successfully');
    }

    public function testHandleCommandWithOptions(): void
    {
        // Arrange
        $args = ['cli', 'list', '--all'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'Command with options should execute successfully');
    }

    public function testHandleGlobalOptionsBeforeCommand(): void
    {
        // Arrange
        $args = ['cli', '--verbose', 'list'];

        // Act
        $exitCode = $this->handler->handle($args);

        // Assert
        $this->assertSame(0, $exitCode, 'Global options before command should work');
    }

    public function testKebabCaseActionConversion(): void
    {
        // Arrange & Act & Assert
        // Test that kebab-case actions are converted to camelCase method names
        $this->assertSame(
            'createBackup',
            \Switon\Core\Naming::camel('create-backup'),
            'Kebab-case action should convert to camelCase'
        );
        $this->assertSame(
            'deleteOldFiles',
            \Switon\Core\Naming::camel('delete-old-files'),
            'Multi-word kebab-case should convert correctly'
        );
        $this->assertSame(
            'default',
            \Switon\Core\Naming::camel('default'),
            'Single word should remain unchanged'
        );
    }

}
