<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Command\CommandDiscovery;

#[AllowMockObjectsWithoutExpectations]
class CommandDiscoveryExtractCommandNameTest extends TestCase
{
    public function testExtractCommandNameReturnsNullWhenClassDoesNotEndWithCommand(): void
    {
        $discovery = new TestableCommandDiscovery();
        $this->assertNull($discovery->exposedExtractCommandName('App\\Command\\FooController'));
        $this->assertNull($discovery->exposedExtractCommandName('Foo'));
    }

    public function testExtractCommandNameConvertsSuffixToKebabCaseName(): void
    {
        $discovery = new TestableCommandDiscovery();
        $this->assertSame('help', $discovery->exposedExtractCommandName('Switon\\Cli\\Command\\HelpCommand'));
        $this->assertSame('backup-database', $discovery->exposedExtractCommandName('App\\Command\\BackupDatabaseCommand'));
        $this->assertSame('http-server', $discovery->exposedExtractCommandName('App\\Command\\HTTPServerCommand'));
    }
}

class TestableCommandDiscovery extends CommandDiscovery
{
    public function exposedExtractCommandName(string $className): ?string
    {
        return $this->extractCommandName($className);
    }
}
