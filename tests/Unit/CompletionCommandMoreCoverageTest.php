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
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class CompletionCommandMoreCoverageTest extends TestCase
{
    public function testCheckActionReturnsErrorWhenHomeMissing(): void
    {
        putenv('HOME'); // unset

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())->method('error')->with('Cannot resolve HOME for completion check.')->willReturn(1);

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(1, $cmd->checkAction());
    }

    public function testCheckActionErrorsWhenNoScriptsFound(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->method('section');
        $console->method('writeLn');
        $console->method('warning');
        $console->method('success');
        $console->expects($this->once())
            ->method('error')
            ->with('No completion scripts found. Run completion:install first.')
            ->willReturn(5);

        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('exists')->willReturn(false);

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(5, $cmd->checkAction());
    }

    public function testBestEffortClearZcompdumpDeletesCachesAndSurvivesDeleteFailure(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())->method('success')->with('zsh: cleared ~/.zcompdump* cache');

        $deleted = [];
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('glob')->willReturn(['/home/demo/.zcompdump', '/home/demo/.zcompdump-1']);
        $fs->method('delete')->willReturnCallback(static function (string $path) use (&$deleted): void {
            $deleted[] = $path;
            if (str_ends_with($path, '-1')) {
                throw new RuntimeException('cannot delete');
            }
        });

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $cmd->exposedBestEffortClearZcompdump();
        $this->assertCount(2, $deleted);
    }

    public function testBestEffortClearZcompdumpWarnsWhenGlobThrows(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())
            ->method('warning')
            ->with('zsh: failed to clear ~/.zcompdump* cache; restart shell if completion is stale');

        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('glob')->willThrowException(new RuntimeException('glob failed'));

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $cmd->exposedBestEffortClearZcompdump();
        $this->addToAssertionCount(1);
    }

    public function testCompleteActionCompletesOptionValueUsingFileGlobWhenCurrentHasSlash(): void
    {
        putenv('SWITON_COMPLETION_SHELL=zsh');

        $console = $this->createMock(ConsoleInterface::class);
        $written = [];
        $console->method('writeLn')->willReturnCallback(static function (string $line) use (&$written): void {
            $written[] = $line;
        });

        // position=4, args: [entry, cmd, action, prevArg, currentValue]
        $router = $this->createMock(RouterInterface::class);
        $router->method('getParams')->willReturn(['4', 'switon', 'demo', 'default', '--path', '/tmp/pa']);

        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionPathCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'd']);

        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('glob')->willReturn(['/tmp/pathA', '/tmp/pathB/']);
        $fs->method('isDir')->willReturnCallback(static fn (string $p): bool => str_ends_with($p, '/'));

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $discovery,
            $inspector,
            $fs,
            $this->createMock(MakerInterface::class),
            $router,
        );

        $this->assertSame(0, $cmd->completeAction());
        $last = $written[array_key_last($written)];
        $this->assertStringContainsString('/tmp/pathA', $last);
    }

    public function testCheckActionSucceedsAndEmitsWarningsWhenAutoloadLikelyNo(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->method('section');
        $console->method('writeLn');
        $console->expects($this->exactly(2))->method('warning');
        $console->expects($this->once())->method('success')->with('Completion check finished.');
        $console->method('error')->willReturn(9);

        $files = [
            '/home/demo/.bash_completion.d/switon' => 'x',
            '/home/demo/.zsh/site-functions-switon/_switon' => 'y',
            // no hints in rc files => likely no
            '/home/demo/.bashrc' => '',
            '/home/demo/.zshrc' => '',
        ];

        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('exists')->willReturnCallback(static fn (string $p): bool => array_key_exists($p, $files));
        $fs->method('read')->willReturnCallback(static fn (string $p): string => (string)($files[$p] ?? ''));

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(0, $cmd->checkAction());
    }

    public function testInstallActionFailsWhenNoSupportedShellAvailable(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())
            ->method('error')
            ->with('Completion install failed for all detected shells.')
            ->willReturn(7);

        $cmd = new CompletionCommandInstallFailureHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(7, $cmd->installAction());
    }

    public function testInstallBashActionErrorsWhenHomeMissing(): void
    {
        putenv('HOME'); // unset

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())
            ->method('error')
            ->with('bash: HOME is not set; cannot install completion into user directories.')
            ->willReturn(3);

        $fs = $this->createMock(FilesystemInterface::class);

        $cmd = new CompletionCommandInstallFailureHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );
        $cmd->forceShell = 'bash';

        $this->assertSame(3, $cmd->installBashAction());
    }

    public function testInstallBashActionFailsWhenNoWritableCandidatePath(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())
            ->method('error')
            ->with('bash: failed to install completion script: {reason}', $this->callback(static fn (array $ctx): bool => ($ctx['reason'] ?? '') !== ''))
            ->willReturn(4);

        $fs = $this->createMock(FilesystemInterface::class);

        $cmd = new CompletionCommandInstallFailureHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );
        $cmd->forceShell = 'bash';
        $cmd->forceWritable = false;

        $this->assertSame(4, $cmd->installBashAction());
    }

    public function testGetCommandEntryCandidatesHandlesBareAndInlineModes(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn([
            'db' => DummyCompletionEntryCommand::class,
            'solo' => DummyCompletionSoloCommand::class,
            'hidden' => DummyCompletionHiddenCommand::class,
        ]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturnCallback(static fn (string $class): bool => $class === DummyCompletionHiddenCommand::class);
        $inspector->method('getActions')->willReturnCallback(static function (string $class, bool $includeHidden = false): array {
            return match ($class) {
                DummyCompletionEntryCommand::class => ['default' => 'default', 'listUsers' => 'list'],
                DummyCompletionSoloCommand::class => ['default' => 'default'],
                default => [],
            };
        });

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(
            ['db:list-users', 'solo'],
            $cmd->exposedGetCommandEntryCandidates(true)
        );
        $this->assertSame(
            ['db', 'db:list-users', 'solo'],
            $cmd->exposedGetCommandEntryCandidates(false)
        );
    }

    public function testGetArgumentValuesUsesCustomCompletionCallback(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionValueCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'default']);

        $maker = $this->createMock(MakerInterface::class);
        $maker->method('make')->willReturnCallback(static fn (string $class): object => new $class());

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $maker,
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(
            ['alpha', 'beta'],
            $cmd->exposedGetArgumentValues('demo', 'default', '--mode', 'al')
        );
    }

    public function testGetArgumentValuesFallsBackToFilesystemForSlashPrefix(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionPathCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'default']);

        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->method('glob')->willReturn(['/tmp/alpha', '/tmp/beta']);
        $filesystem->method('isDir')->willReturnCallback(static fn (string $path): bool => $path === '/tmp/beta');

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $filesystem,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(
            ['/tmp/alpha', '/tmp/beta/'],
            $cmd->exposedGetArgumentValues('demo', 'default', '--path', '/tmp/pa')
        );
    }

    public function testGetArgumentValuesFallsBackToCurrentDirectoryForWordPrefix(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionScalarCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'default']);

        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->method('glob')->with('./*')->willReturn(['./alpha.php', './beta']);
        $filesystem->method('isDir')->willReturnCallback(static fn (string $path): bool => $path === './beta');

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $filesystem,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(
            ['alpha.php', 'beta/'],
            $cmd->exposedGetArgumentValues('demo', 'default', '--name', 'pa')
        );
    }

    public function testGetArgumentValuesReturnsEmptyWhenNoFallbackApplies(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn(['demo' => DummyCompletionScalarCommand::class]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'default']);

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame([], $cmd->exposedGetArgumentValues('demo', 'default', '--name', '--'));
    }

    public function testGetDefaultArgumentValuesReturnsBoolCandidatesForBooleanParameters(): void
    {
        $cmd = new CompletionCommandMoreCoverageHarness();
        $this->assertSame(
            ['true', 'false', '1', '0', 'yes', 'no'],
            $cmd->exposedGetDefaultArgumentValues(DummyCompletionBoolCommand::class, 'defaultCompletion', '--dry-run')
        );
    }

    public function testGetDefaultArgumentValuesReturnsEmptyForNonBooleanParameters(): void
    {
        $cmd = new CompletionCommandMoreCoverageHarness();
        $this->assertSame(
            [],
            $cmd->exposedGetDefaultArgumentValues(DummyCompletionScalarCommand::class, 'defaultCompletion', '--name')
        );
    }

    public function testGetDefaultArgumentValuesReturnsEmptyWhenActionIsMissing(): void
    {
        $cmd = new CompletionCommandMoreCoverageHarness();
        $this->assertSame(
            [],
            $cmd->exposedGetDefaultArgumentValues(DummyCompletionNoActionCommand::class, 'defaultCompletion', '--name')
        );
    }

    public function testGetCommandEntryCandidatesSkipsRedundantActionNamedLikeCommand(): void
    {
        $discovery = $this->createMock(CommandDiscoveryInterface::class);
        $discovery->method('discover')->willReturn([
            'cache' => DummyCompletionRedundantActionCommand::class,
        ]);

        $inspector = $this->createMock(CommandInspectorInterface::class);
        $inspector->method('isHiddenCommand')->willReturn(false);
        $inspector->method('getActions')->willReturn(['default' => 'default', 'cache' => 'cache']);

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $discovery,
            $inspector,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(['cache'], $cmd->exposedGetCommandEntryCandidates(true));
        $this->assertSame(['cache'], $cmd->exposedGetCommandEntryCandidates(false));
    }

    public function testWriteCompletionScriptReportsFailureWhenWriteThrows(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->method('exists')->willReturn(false);
        $filesystem->method('mkdir')->willReturnCallback(static function (): void {
        });
        $filesystem->method('write')->willThrowException(new RuntimeException('disk full'));
        $filesystem->method('chmod')->willReturnCallback(static function (): void {
        });

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $filesystem,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $error = null;
        $result = $cmd->exposedWriteCompletionScript('/tmp/switon', "line\rline", 'zsh', $error);

        $this->assertSame(ConsoleInterface::FAILURE, $result);
        $this->assertSame('write zsh completion script failed: disk full', $error);
    }

    public function testInstallActionReturnsWindowsError(): void
    {
        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->once())
            ->method('error')
            ->with('Windows system is not support shell completion install!')
            ->willReturn(ConsoleInterface::FAILURE);

        $cmd = new CompletionCommandWindowsHarness();
        $cmd->injectAll(
            $console,
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $this->createMock(FilesystemInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertSame(ConsoleInterface::FAILURE, $cmd->installAction());
    }

    public function testAppendBashrcSwitonCompletionLoadWritesBlockAndThenSkipsWhenMarkerExists(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $written = [];
        $content = '';

        $fs->method('exists')->willReturnCallback(static fn (string $path): bool => str_ends_with($path, '/.bashrc'));
        $fs->method('read')->willReturnCallback(static fn (string $path): string => $content);
        $fs->method('write')->willReturnCallback(function (string $path, string $value) use (&$written, &$content): void {
            $written[] = [$path, $value];
            $content = $value;
        });

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertTrue($cmd->exposedAppendBashrcSwitonCompletionLoad('/home/demo', '/home/demo/.bash_completion.d/switon'));
        $this->assertTrue($cmd->exposedAppendBashrcSwitonCompletionLoad('/home/demo', '/home/demo/.bash_completion.d/switon'));
        $this->assertGreaterThanOrEqual(1, count($written));
        $this->assertStringContainsString('switon-cli-completion-load', $content);
    }

    public function testAppendZshrcSwitonCompletionBindReplacesOldBlockWhenMarkerExists(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $written = [];
        $content = "# switon-cli-completion-bind: old\nif [[ -d '/old' ]]; then\nfi";

        $fs->method('exists')->willReturnCallback(static fn (string $path): bool => str_ends_with($path, '/.zshrc'));
        $fs->method('read')->willReturnCallback(static fn (string $path): string => $content);
        $fs->method('write')->willReturnCallback(function (string $path, string $value) use (&$written, &$content): void {
            $written[] = [$path, $value];
            $content = $value;
        });

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertTrue($cmd->exposedAppendZshrcSwitonCompletionBind('/home/demo', '/home/demo/.zsh/site-functions-switon'));
        $this->assertNotEmpty($written);
        $this->assertStringContainsString('switon-cli-completion-compinit', $content);
        $this->assertStringContainsString('compdef _switon switon', $content);
    }

    public function testWriteCompletionScriptToCandidatesUsesFirstWritableCandidate(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $written = [];

        $fs->method('exists')->willReturnCallback(static fn (string $path): bool => $path === '/tmp');
        $fs->method('write')->willReturnCallback(function (string $path, string $value) use (&$written): void {
            $written[] = [$path, $value];
        });

        $cmd = new CompletionCommandInstallFailureHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );
        $cmd->writableMap = [
            '/not-writable/switon' => false,
            '/tmp/switon' => true,
        ];

        $installedPath = null;
        $error = null;
        $result = $cmd->exposedWriteCompletionScriptToCandidates(
            ['/not-writable/switon', '/tmp/switon'],
            'demo',
            'bash',
            $installedPath,
            $error
        );

        $this->assertSame(ConsoleInterface::SUCCESS, $result);
        $this->assertSame('/tmp/switon', $installedPath);
        $this->assertNull($error);
        $this->assertCount(1, $written);
    }

    public function testHasAnyConfigHintsReturnsTrueWhenNeedleFound(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('exists')->willReturnCallback(static fn (string $path): bool => $path === '/home/demo/.zshrc');
        $fs->method('read')->willReturnCallback(static fn (string $path): string => 'autoload -Uz compinit');

        $cmd = new CompletionCommandMoreCoverageHarness();
        $cmd->injectAll(
            $this->createMock(ConsoleInterface::class),
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $fs,
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->assertTrue($cmd->exposedHasAnyConfigHints(['/home/demo/.zshrc'], ['compinit']));
    }
}

class CompletionCommandMoreCoverageHarness extends CompletionCommand
{
    public function injectAll(
        ConsoleInterface          $console,
        CommandDiscoveryInterface $discovery,
        CommandInspectorInterface $inspector,
        FilesystemInterface       $filesystem,
        MakerInterface            $maker,
        RouterInterface           $router,
    ): void {
        $this->console = $console;
        $this->commandDiscovery = $discovery;
        $this->commandInspector = $inspector;
        $this->filesystem = $filesystem;
        $this->maker = $maker;
        $this->router = $router;
    }

    public function exposedBestEffortClearZcompdump(): void
    {
        $this->bestEffortClearZcompdump();
    }

    public function exposedAppendBashrcSwitonCompletionLoad(string $home, string $installedPath): bool
    {
        return $this->appendBashrcSwitonCompletionLoad($home, $installedPath);
    }

    public function exposedAppendZshrcSwitonCompletionBind(string $home, string $installedDir): bool
    {
        return $this->appendZshrcSwitonCompletionBind($home, $installedDir);
    }

    public function exposedHasAnyConfigHints(array $files, array $needles): bool
    {
        return $this->hasAnyConfigHints($files, $needles);
    }

    public function exposedGetCommandEntryCandidates(bool $includeInlineAction = true): array
    {
        return $this->getCommandEntryCandidates($includeInlineAction);
    }

    public function exposedGetArgumentValues(?string $command, ?string $action, string $argumentName, string $current): array
    {
        return $this->getArgumentValues($command, $action, $argumentName, $current);
    }

    public function exposedGetDefaultArgumentValues(string $commandClassName, string $completionMethod, string $argumentName): array
    {
        return $this->getDefaultArgumentValues($commandClassName, $completionMethod, $argumentName);
    }

    public function exposedWriteCompletionScript(string $file, string $content, string $shell, ?string &$error = null): int
    {
        return $this->writeCompletionScript($file, $content, $shell, $error);
    }
}

class DummyCompletionPathCommand
{
    public function defaultAction(string $path = ''): void
    {
    }
}

class DummyCompletionEntryCommand
{
}

class DummyCompletionSoloCommand
{
}

class DummyCompletionValueCommand
{
    public function defaultCompletion(string $argumentName, string $current): array
    {
        return ['alpha', 'beta'];
    }
}

class DummyCompletionScalarCommand
{
    public function defaultAction(string $name = ''): void
    {
    }
}

class DummyCompletionNoActionCommand
{
}

class DummyCompletionRedundantActionCommand
{
    public function defaultAction(): void
    {
    }

    public function cacheAction(): void
    {
    }
}

class CompletionCommandWindowsHarness extends CompletionCommandMoreCoverageHarness
{
    protected function isWindows(): bool
    {
        return true;
    }
}

class CompletionCommandInstallFailureHarness extends CompletionCommandMoreCoverageHarness
{
    public ?string $forceShell = null;
    public bool $forceWritable = true;
    /** @var array<string, bool> */
    public array $writableMap = [];

    protected function isWindows(): bool
    {
        return false;
    }

    protected function isShellAvailable(string $shell): bool
    {
        if ($this->forceShell === null) {
            return false;
        }
        return $shell === $this->forceShell;
    }

    protected function isWritableTargetPath(string $file): bool
    {
        if ($this->writableMap !== []) {
            return $this->writableMap[$file] ?? false;
        }
        return $this->forceWritable;
    }

    public function exposedWriteCompletionScriptToCandidates(
        array   $candidates,
        string  $content,
        string  $shell,
        ?string &$installedPath,
        ?string &$error = null
    ): int {
        return $this->writeCompletionScriptToCandidates($candidates, $content, $shell, $installedPath, $error);
    }
}
