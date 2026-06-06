<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Cli\Command\CompletionCommand;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Testing\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CompletionCommandTest extends TestCase
{
    protected TestableCompletionCommand $command;
    protected CommandDiscoveryInterface&MockObject $commandDiscovery;
    protected CommandInspectorInterface&MockObject $commandInspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new TestableCompletionCommand();
        $this->commandDiscovery = $this->createMock(CommandDiscoveryInterface::class);
        $this->commandInspector = $this->createMock(CommandInspectorInterface::class);

        $this->command = $this->make(TestableCompletionCommand::class, [
            'commandDiscovery' => $this->commandDiscovery,
            'commandInspector' => $this->commandInspector,
        ]);
    }

    public function testFormatPathForDisplayRewritesHomePrefixToTilde(): void
    {
        $this->assertSame('~', $this->command->exposedFormatPathForDisplay('/Users/mark', '/Users/mark'));
        $this->assertSame('~/work/app', $this->command->exposedFormatPathForDisplay('/Users/mark', '/Users/mark/work/app'));
        $this->assertSame('/opt/data', $this->command->exposedFormatPathForDisplay('/Users/mark', '/opt/data'));
    }

    public function testFormatPathForDisplayHandlesEmptyPathAndMissingHome(): void
    {
        $this->assertSame('', $this->command->exposedFormatPathForDisplay('/Users/mark', ''));
        $this->assertSame('/Users/mark/work/app', $this->command->exposedFormatPathForDisplay('', '/Users/mark/work/app'));
    }

    public function testGetVisibleCommandClassReturnsNullForMissingOrHiddenCommands(): void
    {
        $this->commandDiscovery->method('discover')
            ->willReturn(['list' => DummyCliVisibleCommand::class, 'secret' => DummyCliHiddenCommand::class]);

        $this->commandInspector->method('isHiddenCommand')
            ->willReturnCallback(static fn (string $class): bool => $class === DummyCliHiddenCommand::class);

        $this->assertNull($this->command->exposedGetVisibleCommandClass(null));
        $this->assertNull($this->command->exposedGetVisibleCommandClass('missing'));
        $this->assertNull($this->command->exposedGetVisibleCommandClass('secret'));
        $this->assertSame(DummyCliVisibleCommand::class, $this->command->exposedGetVisibleCommandClass('list'));
    }

    public function testGetActionsReturnsKebabNamesAndFiltersRedundantAction(): void
    {
        $this->commandDiscovery->method('discover')
            ->willReturn(['db' => DummyCliVisibleCommand::class]);
        $this->commandInspector->method('isHiddenCommand')->willReturn(false);
        $this->commandInspector->expects($this->once())
            ->method('getActions')
            ->with(DummyCliVisibleCommand::class, false)
            ->willReturn([
                'default' => 'default action',
                'db' => 'redundant',
                'listAll' => 'list action',
            ]);

        $actions = $this->command->exposedGetActions('db');

        $this->assertSame(['default', 'list-all'], $actions);
    }

}

class TestableCompletionCommand extends CompletionCommand
{
    public function exposedFormatPathForDisplay(string $home, string $path): string
    {
        return $this->formatPathForDisplay($home, $path);
    }

    public function exposedGetVisibleCommandClass(?string $command): ?string
    {
        return $this->getVisibleCommandClass($command);
    }

    /**
     * @return array<int, string>
     */
    public function exposedGetActions(?string $command): array
    {
        return $this->getActions($command);
    }
}

class DummyCliVisibleCommand
{
}

class DummyCliHiddenCommand
{
}
