<?php

declare(strict_types=1);

namespace Switon\Cli\Command;

use Switon\Cli\CommandHelpRendererInterface;
use Switon\Command\Attribute\Hidden;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;

/**
 * User-facing help for one command or action.
 *
 * Guidance: Keep command names public and kebab-case; this command only renders help, it does not discover commands itself.
 *
 * Road-signs:
 * - public help entry is <code>command --help</code> or <code>command:action --help</code>
 * - empty help prints usage only
 * - detailed rendering lives in <code>CommandHelpRendererInterface</code>
 *
 * @see \Switon\Cli\CommandHelpRendererInterface
 * @see \Switon\Cli\Router
 * @see \Switon\Cli\Command\ListCommand
 */
#[Hidden]
class HelpCommand
{
    #[Autowired] protected ConsoleInterface $console;

    #[Autowired] protected CommandHelpRendererInterface $renderer;

    /**
     * Show help for a command.
     *
     * @param string $command Command name (e.g. migrate, sword)
     * @param string $action Action name; empty = all actions
     */
    public function defaultAction(string $command = '', string $action = ''): int
    {
        if ($command === '') {
            $this->console->writeLn('Usage: help <command> [action]');
            $this->console->writeLn('Example: help migrate  or  help db info');
            return 0;
        }

        return $this->renderer->render($command, $action);
    }
}
