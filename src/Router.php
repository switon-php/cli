<?php

declare(strict_types=1);

namespace Switon\Cli;

use function array_filter;
use function array_merge;
use function array_shift;
use function array_values;
use function basename;
use function explode;
use function in_array;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Parses argv into command, action, and parameter routing parts.
 *
 * Road-signs:
 * - empty argv and global <code>--help</code> route to <code>list</code>
 * - <code>command --help</code> and <code>command:action --help</code> route to <code>help</code>
 * - public CLI syntax is <code>command</code> or <code>command:action</code>
 *
 * @see \Switon\Cli\RouterInterface
 * @see \Switon\Cli\RouterInterface::parse()
 * @see \Switon\Cli\HandlerInterface::handle()
 * @see \Switon\Cli\Command\ListCommand
 * @see \Switon\Cli\Command\HelpCommand
 */
class Router implements RouterInterface
{
    /** Entrypoint script name (argv[0]). */
    protected string $entrypoint = '';
    /** Resolved command name. */
    protected string $command = '';
    /** Resolved action name. */
    protected string $action = 'default';
    /** @var array<int, string> Parsed command parameters. */
    protected array $params = [];

    /**
     * {@inheritDoc}
     */
    public function parse(array $args): RouterInterface
    {
        $this->entrypoint = array_shift($args) ?? '';

        // Normalize wrapper forms:
        // When invoked via "php switon.php <cmd> ...", argv becomes:
        //   [php, switon.php, <cmd>, ...]
        // After shifting $this->entrypoint ("php"), we must drop "switon.php" so that
        // the next token (<cmd>) can be parsed by the normal routing logic.
        if (basename((string)$this->entrypoint) === 'php' && isset($args[0]) && str_ends_with((string)$args[0], '.php')) {
            array_shift($args);
        }

        // Extract global options (flags before the command)
        $globalOptions = [];
        $hasHelpFlag = false;

        while ($args !== [] && str_starts_with($args[0], '-')) {
            $option = $args[0];
            if ($option === '--help' || $option === '-h') {
                $hasHelpFlag = true;
            }
            $globalOptions[] = array_shift($args);
        }

        // Handle empty args or global help flag
        if ($args === [] || $hasHelpFlag || $args === ['--help'] || $args === ['-h']) {
            $this->command = 'list';
            $this->action = 'default';
            $this->params = array_merge($globalOptions, $args);
            return $this;
        }

        // Parse command and action
        $cmd = array_shift($args);
        $command = '';
        $action = null;

        if (str_contains($cmd, ':')) {
            [$command, $action] = explode(':', $cmd, 2);
        } else {
            $command = $cmd;
        }

        // Command-specific --help: show that command's help via HelpCommand (user-facing)
        $hasCommandHelp = in_array('--help', $args, true);
        if ($hasCommandHelp) {
            $rest = array_values(array_filter($args, static fn (string $a): bool => $a !== '--help'));
            $this->command = 'help';
            $this->action = 'default';
            $this->params = array_merge(
                $globalOptions,
                ['--command', $command],
                $action !== null ? ['--action', $action] : [],
                $rest
            );
            return $this;
        }

        // Set final routing
        $this->command = $command;
        $this->action = $action ?? 'default';
        $this->params = array_merge($globalOptions, $args);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntrypoint(): string
    {
        return $this->entrypoint;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * {@inheritDoc}
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * {@inheritDoc}
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
