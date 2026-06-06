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
class CompletionCommandInstallTest extends TestCase
{
    public function testInstallActionInstallsForBothShellsAndPatchesRcFiles(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->expects($this->atLeastOnce())->method('writeLn');
        $console->expects($this->atLeastOnce())->method('info');
        $console->expects($this->once())
            ->method('success')
            ->with('Completion installed for: {shells}', $this->callback(static function (array $ctx): bool {
                return ($ctx['shells'] ?? '') === 'bash, zsh';
            }));

        $files = [];
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('exists')->willReturnCallback(static function (string $path) use (&$files): bool {
            return array_key_exists($path, $files);
        });
        $fs->method('read')->willReturnCallback(static function (string $path) use (&$files): string {
            if (!array_key_exists($path, $files)) {
                throw new RuntimeException('missing');
            }
            return (string)$files[$path];
        });
        $fs->method('write')->willReturnCallback(static function (string $path, string $content) use (&$files): void {
            $files[$path] = $content;
        });
        $fs->method('mkdir')->willReturnCallback(static function (string $path) use (&$files): void {
            $files[$path] = $files[$path] ?? '__dir__';
        });
        $fs->method('chmod')->willReturnCallback(static function (): void {
        });

        $cmd = new TestableCompletionCommandInstall();
        $cmd->setConsole($console);
        $cmd->setFilesystem($fs);
        $cmd->setDependencies(
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );
        $cmd->forceShells = ['bash' => true, 'zsh' => true];
        $cmd->forceWritable = true;

        $code = $cmd->installAction();

        $this->assertSame(0, $code);

        $this->assertArrayHasKey('/home/demo/.bash_completion.d/switon', $files, 'bash completion script should be written');
        $this->assertArrayHasKey('/home/demo/.bashrc', $files, 'bashrc should be patched');
        $this->assertArrayHasKey('/home/demo/.zsh/site-functions-switon/_switon', $files, 'zsh completion script should be written');
        $this->assertArrayHasKey('/home/demo/.zsh/site-functions-switon/_zsh_switon_php', $files, 'zsh php proxy should be written');
        $this->assertArrayHasKey('/home/demo/.zshrc', $files, 'zshrc should be patched');

        $this->assertStringContainsString('complete -F _switon switon', (string)$files['/home/demo/.bash_completion.d/switon']);
        $this->assertStringContainsString('compdef _switon switon', (string)$files['/home/demo/.zsh/site-functions-switon/_switon']);
        $this->assertStringContainsString('switon-cli-completion-load', (string)$files['/home/demo/.bashrc']);
        $this->assertStringContainsString('switon-cli-completion-bind', (string)$files['/home/demo/.zshrc']);
    }

    public function testInstallZshActionInvokesZcompdumpClearBestEffort(): void
    {
        putenv('HOME=/home/demo');

        $console = $this->createMock(ConsoleInterface::class);
        $console->method('writeLn');
        $console->method('info');
        $console->method('success');

        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('exists')->willReturn(false);
        $fs->method('write')->willReturnCallback(static function (): void {
        });
        $fs->method('mkdir')->willReturnCallback(static function (): void {
        });
        $fs->method('chmod')->willReturnCallback(static function (): void {
        });

        $cmd = new TestableCompletionCommandInstall();
        $cmd->setConsole($console);
        $cmd->setFilesystem($fs);
        $cmd->setDependencies(
            $this->createMock(CommandDiscoveryInterface::class),
            $this->createMock(CommandInspectorInterface::class),
            $this->createMock(MakerInterface::class),
            $this->createMock(RouterInterface::class),
        );
        $cmd->forceShells = ['zsh' => true];
        $cmd->forceWritable = true;

        $code = $cmd->installZshAction();

        $this->assertSame(0, $code);
        $this->assertTrue($cmd->clearedZcompdump, 'installZshAction should clear zcompdump best-effort');
    }
}

class TestableCompletionCommandInstall extends CompletionCommand
{
    /** @var array<string, bool> */
    public array $forceShells = [];
    public bool $forceWritable = true;
    public bool $clearedZcompdump = false;

    public function setConsole(ConsoleInterface $console): void
    {
        $this->console = $console;
    }

    public function setFilesystem(FilesystemInterface $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    public function setDependencies(
        CommandDiscoveryInterface $discovery,
        CommandInspectorInterface $inspector,
        MakerInterface            $maker,
        RouterInterface           $router,
    ): void {
        $this->commandDiscovery = $discovery;
        $this->commandInspector = $inspector;
        $this->maker = $maker;
        $this->router = $router;
    }

    protected function isWindows(): bool
    {
        return false;
    }

    protected function isShellAvailable(string $shell): bool
    {
        return $this->forceShells[$shell] ?? false;
    }

    protected function isWritableTargetPath(string $file): bool
    {
        return $this->forceWritable;
    }

    protected function bestEffortClearZcompdump(): void
    {
        $this->clearedZcompdump = true;
    }
}
