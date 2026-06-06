<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * CLI command execution completed event.
 *
 * Log category: <code>switon.cli.command.executed</code>
 * Payload: command name, raw argument string, and elapsed seconds.
 *
 * @see \Switon\Cli\Handler
 * @see \Switon\Cli\Event\CommandExecuting
 */
#[EventLevel(Severity::INFO)]
class CommandExecuted
{
    /** @param float $elapsed Command execution time in seconds. */
    public function __construct(
        public string $command,
        public string $args,
        public float  $elapsed,
    ) {
    }
}
