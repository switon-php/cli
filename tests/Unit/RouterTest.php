<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Switon\Cli\Router;
use Switon\Cli\Tests\TestCase;

/**
 * Test cases for Router class.
 *
 * Tests command-line argument parsing and routing logic.
 */
class RouterTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    public function testParseSimpleCommand(): void
    {
        // Arrange
        $args = ['cli', 'migrate'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('cli', $this->router->getEntrypoint(), 'Entrypoint should be cli');
        $this->assertSame('migrate', $this->router->getCommand(), 'Command should be migrate');
        $this->assertSame('default', $this->router->getAction(), 'Action should default to default');
        $this->assertSame([], $this->router->getParams(), 'Params should be empty');
    }

    public function testParseCommandWithColonAction(): void
    {
        // Arrange
        $args = ['cli', 'user:create'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('user', $this->router->getCommand(), 'Command should be user');
        $this->assertSame('create', $this->router->getAction(), 'Action should be create');
    }

    public function testParseEmptyArgsShowsList(): void
    {
        // Arrange
        $args = ['cli'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('list', $this->router->getCommand(), 'Empty args should route to list');
        $this->assertSame('default', $this->router->getAction(), 'Action should be default');
    }

    public function testParseGlobalHelpFlag(): void
    {
        // Arrange
        $args = ['cli', '--help'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('list', $this->router->getCommand(), 'Help flag should route to list');
        $this->assertSame('default', $this->router->getAction(), 'Action should be default');
    }

    public function testParseShortHelpFlag(): void
    {
        // Arrange
        $args = ['cli', '-h'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('list', $this->router->getCommand(), 'Short help flag should route to list');
    }

    public function testParseCommandSpecificHelp(): void
    {
        // migrate --help → help with --command migrate (HelpCommand, user-facing)
        $args = ['cli', 'migrate', '--help'];

        $this->router->parse($args);

        $this->assertSame('help', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $this->assertContains('--command', $this->router->getParams());
        $this->assertContains('migrate', $this->router->getParams());
    }

    public function testParseCommandActionSpecificHelp(): void
    {
        // user:create --help → help with --command user --action create
        $args = ['cli', 'user:create', '--help'];

        $this->router->parse($args);

        $this->assertSame('help', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $this->assertContains('--action', $this->router->getParams());
        $this->assertContains('create', $this->router->getParams());
    }

    public function testParseCommandHelpWithOptions(): void
    {
        $args = ['cli', 'db:list', '--help', '--json'];

        $this->router->parse($args);

        $this->assertSame('help', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $params = $this->router->getParams();
        $this->assertContains('--command', $params);
        $this->assertContains('db', $params);
        $this->assertContains('--action', $params);
        $this->assertContains('list', $params);
        $this->assertContains('--json', $params);
    }

    public function testParseListCommandHelpRoutesToHelp(): void
    {
        $args = ['cli', 'list', '--help'];

        $this->router->parse($args);

        $this->assertSame('help', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $this->assertContains('--command', $this->router->getParams());
        $this->assertContains('list', $this->router->getParams());
    }

    public function testParseCommandWithShortHDoesNotRedirectToHelp(): void
    {
        // -h is not help for command; it may be a command option (e.g. --host)
        $args = ['cli', 'db:list', '-h'];

        $this->router->parse($args);

        $this->assertSame('db', $this->router->getCommand());
        $this->assertSame('list', $this->router->getAction());
        $this->assertContains('-h', $this->router->getParams());
    }

    public function testParseGlobalOptionsBeforeCommand(): void
    {
        // Arrange
        $args = ['cli', '--verbose', 'migrate'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('migrate', $this->router->getCommand(), 'Command should be migrate');
        $this->assertContains('--verbose', $this->router->getParams(), 'Params should contain --verbose');
    }

    public function testParseCommandWithParams(): void
    {
        // Arrange
        $args = ['cli', 'migrate', '--step', '5'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('migrate', $this->router->getCommand(), 'Command should be migrate');
        $this->assertContains('--step', $this->router->getParams(), 'Params should contain --step');
        $this->assertContains('5', $this->router->getParams(), 'Params should contain 5');
    }

    public function testParseReturnsFluentInterface(): void
    {
        // Arrange
        $args = ['cli', 'test'];

        // Act
        $result = $this->router->parse($args);

        // Assert
        $this->assertSame($this->router, $result, 'Parse should return self for fluent interface');
    }

    public function testParseMultipleGlobalOptions(): void
    {
        // Arrange
        $args = ['cli', '--verbose', '--debug', 'migrate'];

        // Act
        $this->router->parse($args);

        // Assert
        $this->assertSame('migrate', $this->router->getCommand(), 'Command should be migrate');
        $this->assertContains('--verbose', $this->router->getParams(), 'Params should contain --verbose');
        $this->assertContains('--debug', $this->router->getParams(), 'Params should contain --debug');
    }

    public function testParsePhpWrapperDropsScriptArgSoCommandParsesNormally(): void
    {
        // php switon.php list --json
        $args = ['php', 'switon.php', 'list', '--json'];

        $this->router->parse($args);

        $this->assertSame('php', $this->router->getEntrypoint());
        $this->assertSame('list', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $this->assertSame(['--json'], $this->router->getParams());
    }

    public function testParsePhpWrapperWithGlobalOptionsBeforeCommand(): void
    {
        // php switon.php --verbose migrate --step 1
        $args = ['php', 'switon.php', '--verbose', 'migrate', '--step', '1'];

        $this->router->parse($args);

        $this->assertSame('migrate', $this->router->getCommand());
        $this->assertSame('default', $this->router->getAction());
        $this->assertSame(['--verbose', '--step', '1'], $this->router->getParams());
    }

}
