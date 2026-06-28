<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Cli\ServerInterface;

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
class CommandExecuting implements SenderAwareInterface
{
    /** @param string $args Command-line argument string. */
    public function __construct(
        public ServerInterface $sender,
        public string $command,
        public string $args,
    ) {
    }

    public function getSender(): ServerInterface
    {
        return $this->sender;
    }
}
