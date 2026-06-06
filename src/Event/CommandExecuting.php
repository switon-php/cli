<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * CLI command execution begins event.
 *
 * Log category: <code>switon.cli.command.executing</code>
 * Payload: command name and raw argument string.
 *
 * @see \Switon\Cli\Handler
 * @see \Switon\Cli\Event\CommandExecuted
 */
#[EventLevel(Severity::INFO)]
class CommandExecuting
{
    /** @param string $args Command-line argument string. */
    public function __construct(
        public string $command,
        public string $args,
    ) {
    }
}
