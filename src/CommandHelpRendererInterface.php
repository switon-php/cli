<?php

declare(strict_types=1);

namespace Switon\Cli;

/**
 * Renders human-readable help for one command (and optionally one action).
 * Used by HelpCommand (user-facing) and by Beacon's ToolCommand (AI tool delegates human output).
 *
 * Guidance: Pass public CLI names here; command and action use kebab-case, matching discovery keys and help/list output.
 *
 * Road-signs:
 * - command is a discovery key
 * - action is a public kebab-case action name
 * - empty action means render all actions
 *
 * @see \Switon\Cli\Command\HelpCommand Typical consumer
 * @see \Switon\Beacon\Command\ToolCommand Typical consumer
 * @see \Switon\Command\CommandDiscoveryInterface
 * @see \Switon\Command\CommandDiscoveryInterface::discover()
 * @see \Switon\Core\Naming::camel()
 */
interface CommandHelpRendererInterface
{
    /**
     * Render help for a command to the console.
     *
     * @param string $command Public command name in kebab-case (for example migrate, backup-database)
     * @param string $action Public action name in kebab-case; empty = all actions
     *
     * @return int Exit code (0 on success)
     */
    public function render(string $command, string $action = ''): int;
}
