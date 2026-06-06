<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Cli\Command\HelpCommand;
use Switon\Cli\CommandHelpRendererInterface;
use Switon\Core\ConsoleInterface;
use Switon\Testing\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HelpCommandTest extends TestCase
{
    protected HelpCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected CommandHelpRendererInterface&MockObject $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->console = $this->createMock(ConsoleInterface::class);
        $this->renderer = $this->createMock(CommandHelpRendererInterface::class);
        $this->command = $this->make(HelpCommand::class, [
            'console' => $this->console,
            'renderer' => $this->renderer,
        ]);
    }

    public function testDefaultActionPrintsUsageWhenCommandIsMissing(): void
    {
        $lines = [];
        $this->console->expects($this->exactly(2))
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            });
        $this->renderer->expects($this->never())->method('render');

        $code = $this->command->defaultAction('');

        $this->assertSame(0, $code);
        $this->assertSame('Usage: help <command> [action]', $lines[0]);
        $this->assertSame('Example: help migrate  or  help db info', $lines[1]);
    }

    public function testDefaultActionDelegatesToRendererWhenCommandIsProvided(): void
    {
        $this->console->expects($this->never())->method('writeLn');
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('db', 'list')
            ->willReturn(7);

        $code = $this->command->defaultAction('db', 'list');

        $this->assertSame(7, $code);
    }
}
