<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Cli\Command\CompletionCommand;
use Switon\Cli\RouterInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\MakerInterface;

#[AllowMockObjectsWithoutExpectations]
class CompletionCommandEdgeCasesTest extends TestCase
{
    public function testCompleteActionCompletesArgumentNameAfterBareDoubleDash(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        $router = $this->createMock(RouterInterface::class);
        // position=4, args include previous "--" and current "" => should list argument names
        $router->method('getParams')->willReturn(['4', 'switon', 'demo', 'default', '--', '']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionCustomCompletionCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd']);

        $maker = $this->createMock(MakerInterface::class);
        $maker->method('make')->willReturn(new DummyCompletionCustomCompletionCommand());

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $maker,
            $router,
        );

        $this->assertSame(0, $cmd->completeAction());
        $last = $written[array_key_last($written)];
        $this->assertStringContainsString('--name', $last);
    }

    public function testCompleteActionCompletesArgumentValueUsingCustomCompletionMethod(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        // current token uses --name=al form; should call maker completion and prefix output with --name=
        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['3', 'switon', 'demo', 'default', '--name=al']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionCustomCompletionCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd']);

        $maker = $this->createMock(MakerInterface::class);
        $maker->method('make')->willReturn(new DummyCompletionCustomCompletionCommand());

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $maker,
            $router,
        );

        $this->assertSame(0, $cmd->completeAction());
        $last = $written[array_key_last($written)];
        $this->assertStringContainsString('--name=alice', $last);
        $this->assertStringContainsString('--name=albert', $last);
    }

    public function testFilterWordsFuzzyFallbackMatchesCharactersInOrder(): void
    {
        $cmd = new CompletionCommandFilterHarness();
        $words = ['backup-database', 'build-cache', 'list'];
        $filtered = $cmd->exposedFilterWords($words, 'bd');
        $this->assertContains('backup-database', $filtered);
    }

    public function testFilterWordsReturnsOriginalListWhenCurrentIsEmpty(): void
    {
        $cmd = new CompletionCommandFilterHarness();
        $words = ['cache:clear', 'cache:get', 'cache:set'];

        $filtered = $cmd->exposedFilterWords($words, '');

        $this->assertSame($words, $filtered);
    }

    public function testCompleteActionTreatsDashedActionTokenAsDefaultActionWithoutFailure(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['2', 'switon', 'demo', '--na']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionCustomCompletionCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd']);

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $router,
        );

        $this->assertSame(0, $cmd->completeAction());
        $last = $written[array_key_last($written)];
        $this->assertSame('', $last);
    }
}

class DummyCompletionCustomCompletionCommand
{
    public function defaultAction(string $name = ''): void
    {
    }

    /** @return list<string> */
    public function defaultCompletion(string $argumentName, string $current): array
    {
        if ($argumentName === '--name' && $current === 'al') {
            return ['alice', 'albert'];
        }
        return [];
    }
}

class CompletionCommandFilterHarness extends CompletionCommand
{
    /** @return list<string> */
    public function exposedFilterWords(array $words, string $current): array
    {
        return $this->filterWords($words, $current);
    }
}
