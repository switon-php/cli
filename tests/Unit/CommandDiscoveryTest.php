<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Switon\Cli\Tests\TestCase;
use Switon\Command\CommandDiscovery;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\ClassScannerInterface;
use RuntimeException;

/**
 * Test cases for CommandDiscovery class.
 *
 * Tests command discovery from composer.json and application directories.
 */
class CommandDiscoveryTest extends TestCase
{
    protected CommandDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = $this->container->make(CommandDiscovery::class);
    }

    public function testGetCommandsReturnsArray(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();

        // Assert
        $this->assertIsArray($commands, 'Commands should be an array');
    }

    public function testGetCommandsContainsListCommand(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();

        // Assert
        $this->assertArrayHasKey('list', $commands, 'Should contain list command');
        $this->assertIsString($commands['list'], 'Command class should be string');
    }

    public function testGetCommandsContainsBuiltinCommands(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();

        // Assert
        $this->assertNotEmpty($commands, 'Should discover at least some commands');

        // All command names should be kebab-case
        foreach ($commands as $name => $class) {
            $this->assertIsString($name, 'Command name should be string');
            $this->assertIsString($class, 'Command class should be string');
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/',
                $name,
                'Command name should be kebab-case'
            );
        }
    }

    public function testGetCommandsAreSorted(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();
        $keys = array_keys($commands);
        $sortedKeys = $keys;
        sort($sortedKeys);

        // Assert
        $this->assertSame($sortedKeys, $keys, 'Commands should be sorted alphabetically');
    }

    public function testGetCommandsCachesResults(): void
    {
        // Arrange & Act
        $commands1 = $this->discovery->discover();
        $commands2 = $this->discovery->discover();

        // Assert
        $this->assertSame($commands1, $commands2, 'Subsequent calls should return cached results');
    }

    public function testCommandClassNamesEndWithCommand(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();

        // Assert
        foreach ($commands as $name => $class) {
            $this->assertStringEndsWith(
                'Command',
                $class,
                "Command class '$class' should end with 'Command'"
            );
        }
    }

    public function testCommandNamesMappedFromClassName(): void
    {
        // Arrange & Act
        $commands = $this->discovery->discover();

        // Assert
        if (isset($commands['list'])) {
            $className = $commands['list'];
            $this->assertStringContainsString(
                'List',
                $className,
                'List command class should contain "List"'
            );
        }
    }

    public function testMultipleInstancesShareCache(): void
    {
        // Arrange
        $discovery1 = $this->container->make(CommandDiscovery::class);
        $discovery2 = $this->container->make(CommandDiscovery::class);

        // Act
        $commands1 = $discovery1->discover();
        $commands2 = $discovery2->discover();

        // Assert
        $this->assertEquals(
            $commands1,
            $commands2,
            'Different instances should discover same commands'
        );
    }

    public function testKebabCaseConversion(): void
    {
        // Arrange & Act & Assert
        $this->assertSame(
            'help',
            \Switon\Core\Naming::kebab('Help'),
            'Single word should be lowercased'
        );
        $this->assertSame(
            'user',
            \Switon\Core\Naming::kebab('User'),
            'Single word should be lowercased'
        );
        $this->assertSame(
            'backup-database',
            \Switon\Core\Naming::kebab('BackupDatabase'),
            'PascalCase should convert to kebab-case'
        );
        $this->assertSame(
            'http-server',
            \Switon\Core\Naming::kebab('HTTPServer'),
            'Consecutive capitals should be handled'
        );
        $this->assertSame(
            'user-create',
            \Switon\Core\Naming::kebab('UserCreate'),
            'Two words should be hyphenated'
        );
    }

    public function testDiscoverMergesComposerAndScannerAndScannerOverridesSameCommandName(): void
    {
        $composer = $this->createMock(ComposerExtraInterface::class);
        $composer->expects($this->once())->method('collect')->with('switon.commands')->willReturn([
            'Vendor\\Pkg\\AlphaCommand',
            'Vendor\\Pkg\\IgnoredClass',
            'Vendor\\Pkg\\DupCommand',
        ]);

        $scanner = $this->createMock(ClassScannerInterface::class);
        $scanner->expects($this->once())->method('scan')->with([
            '@custom/*Command.php',
            '@app/Command/*Command.php' => 'App\\Command\\*Command',
            '@app/Areas/*/Command/*Command.php' => 'App\\Areas\\*\\Command\\*Command',
        ])->willReturn([
            'App\\Command\\DupCommand',
            'App\\Command\\BetaCommand',
        ]);

        $discovery = $this->make(CommandDiscovery::class, [
            'composerExtra' => $composer,
            'classScanner' => $scanner,
            'files' => ['@custom/*Command.php'],
        ]);

        $result = $discovery->discover();

        $this->assertArrayHasKey('alpha', $result);
        $this->assertSame('Vendor\\Pkg\\AlphaCommand', $result['alpha']);
        $this->assertArrayHasKey('beta', $result);
        $this->assertSame('App\\Command\\BetaCommand', $result['beta']);
        $this->assertArrayHasKey('dup', $result);
        $this->assertSame('App\\Command\\DupCommand', $result['dup'], 'Scanner result should override composer for same name');
        $this->assertArrayNotHasKey('ignored-class', $result);

        // Cached result: no second scan/collect call.
        $this->assertSame($result, $discovery->discover());
    }

    public function testDiscoverExcludesByClassPrefixes(): void
    {
        $composer = $this->createMock(ComposerExtraInterface::class);
        $composer->expects($this->once())->method('collect')->with('switon.commands')->willReturn([
            'Vendor\\Pkg\\AlphaCommand',
            'Switon\\System\\Command\\OpsCommand',
        ]);

        $scanner = $this->createMock(ClassScannerInterface::class);
        $scanner->expects($this->once())->method('scan')->with([
            '@custom/*Command.php',
            '@app/Command/*Command.php' => 'App\\Command\\*Command',
            '@app/Areas/*/Command/*Command.php' => 'App\\Areas\\*\\Command\\*Command',
        ])->willReturn([
            'App\\Command\\BetaCommand',
            'App\\Command\\Internal\\LocalSecretCommand',
            'Switon\\System\\Command\\DoctorCommand',
        ]);

        $discovery = $this->make(CommandDiscovery::class, [
            'composerExtra' => $composer,
            'classScanner' => $scanner,
            'files' => ['@custom/*Command.php'],
            'excludes' => [
                'Switon\\System\\Command\\',
                'App\\Command\\Internal\\',
            ],
        ]);

        $result = $discovery->discover();

        $this->assertArrayHasKey('alpha', $result);
        $this->assertArrayHasKey('beta', $result);
        $this->assertArrayNotHasKey('ops', $result, 'Excluded namespace prefix should filter composer commands');
        $this->assertArrayNotHasKey('doctor', $result, 'Excluded namespace prefix should filter scanned commands');
        $this->assertArrayNotHasKey('local-secret', $result, 'Excluded namespace prefix should filter app commands');
    }

    public function testDiscoverWithAppOnlySkipsComposerCommands(): void
    {
        $composer = $this->createMock(ComposerExtraInterface::class);
        $composer->expects($this->never())->method('collect');

        $scanner = $this->createMock(ClassScannerInterface::class);
        $scanner->expects($this->once())->method('scan')->with([
            '@custom/*Command.php',
            '@app/Command/*Command.php' => 'App\\Command\\*Command',
            '@app/Areas/*/Command/*Command.php' => 'App\\Areas\\*\\Command\\*Command',
        ])->willReturn([
            'App\\Command\\BetaCommand',
        ]);

        $discovery = $this->make(CommandDiscovery::class, [
            'composerExtra' => $composer,
            'classScanner' => $scanner,
            'files' => ['@custom/*Command.php'],
            'app_only' => true,
        ]);

        $result = $discovery->discover();

        $this->assertSame(['beta' => 'App\\Command\\BetaCommand'], $result);
    }

    public function testDiscoverKeepsScannerResultsWhenComposerExtraFails(): void
    {
        $composer = $this->createMock(ComposerExtraInterface::class);
        $composer->expects($this->once())
            ->method('collect')
            ->with('switon.commands')
            ->willThrowException(new RuntimeException('cache missing'));

        $scanner = $this->createMock(ClassScannerInterface::class);
        $scanner->expects($this->once())->method('scan')->with([
            '@custom/*Command.php',
            '@app/Command/*Command.php' => 'App\\Command\\*Command',
            '@app/Areas/*/Command/*Command.php' => 'App\\Areas\\*\\Command\\*Command',
        ])->willReturn([
            'App\\Command\\BetaCommand',
        ]);

        $discovery = $this->make(CommandDiscovery::class, [
            'composerExtra' => $composer,
            'classScanner' => $scanner,
            'files' => ['@custom/*Command.php'],
        ]);

        $result = $discovery->discover();

        $this->assertSame(['beta' => 'App\\Command\\BetaCommand'], $result);
    }
}
